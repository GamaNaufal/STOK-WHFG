"""Create a geometry-equivalent render copy without editing the authored master.

Usage (Blender 5.3):
  blender -b artifacts/warehouse-login/warehouse_fg_login_master.blend \
    --python scripts/blender/optimize_warehouse_login_render.py -- \
    --output C:/Temp/warehouse_fg_login_render.blend --benchmark 180 181

Rigid mesh descendants are evaluated once and grouped by their nearest animated
or constrained ancestor and material slots. Vertices are baked into that
ancestor's local space, so vehicles, fork carriages, and transferred pallets
retain their original animation. Wheels, rollers, deforming meshes, cameras,
lights, fonts, materials, compositor, timing and the original file stay intact.
"""

import argparse
from collections import defaultdict
import json
from pathlib import Path
import sys
import time

import bpy


def group_anchor(obj, referenced_objects):
    """Return (eligible, nearest changing ancestor); None is the static world."""
    if (obj.type != 'MESH' or obj.hide_render or obj.animation_data or obj.constraints
            or obj.children or obj in referenced_objects):
        return False, None
    if obj.data.animation_data or obj.data.shape_keys:
        return False, None
    if any(modifier.type not in {'BEVEL', 'WEIGHTED_NORMAL'} for modifier in obj.modifiers):
        return False, None
    parent = obj.parent
    while parent:
        if parent.animation_data or parent.constraints:
            return True, parent
        parent = parent.parent
    return True, None


def constraint_targets(scene):
    references = {}
    objects = set()
    for obj in scene.objects:
        for constraint in obj.constraints:
            for prop in constraint.bl_rna.properties:
                if prop.type != 'POINTER':
                    continue
                value = getattr(constraint, prop.identifier, None)
                if isinstance(value, bpy.types.Object):
                    references[f'{obj.name}/{constraint.name}/{prop.identifier}'] = value.name
                    objects.add(value)
        if obj.animation_data:
            for curve in obj.animation_data.drivers:
                for variable in curve.driver.variables:
                    for target in variable.targets:
                        if isinstance(target.id, bpy.types.Object):
                            objects.add(target.id)
    return references, objects


PALLET_ROOTS = ['Inbound pallet to rack B4-02-L2', 'Picked pallet to truck dock 01']
POSE_FRAMES = [1, 180, 500, 930, 1020, 1110, 1230, 1231, 1300, 1350, 1440,
               1530, 1600, 1800, 1920, 1965, 2050, 2100, 2150, 2180, 2220,
               2280, 2281, 2370, 2460, 2700]
BOUNDS_FRAMES = [180, 1020, 1600, 2050]


def pallet_bounds(scene, depsgraph, root_name):
    root = scene.objects.get(root_name)
    if root is None:
        raise AssertionError(f'Missing pallet rig: {root_name}')
    points = []
    for obj in root.children_recursive:
        if obj.type == 'MESH':
            evaluated = obj.evaluated_get(depsgraph)
            points.extend(evaluated.matrix_world @ vertex.co
                          for vertex in evaluated.data.vertices)
    if not points:
        raise AssertionError(f'No pallet geometry under {root_name}')
    return [min(point[axis] for point in points) for axis in range(3)] + [
        max(point[axis] for point in points) for axis in range(3)]


def pose_snapshot(scene, tracked_names):
    result = {}
    for frame in POSE_FRAMES:
        scene.frame_set(frame)
        depsgraph = bpy.context.evaluated_depsgraph_get()
        result[frame] = {'matrices': {}, 'pallet_bounds': {}}
        for name in tracked_names:
            obj = scene.objects.get(name)
            if obj is None:
                raise AssertionError(f'An animation helper was removed: {name}')
            matrix = obj.evaluated_get(depsgraph).matrix_world
            result[frame]['matrices'][name] = [value for row in matrix for value in row]
        if frame in BOUNDS_FRAMES:
            for root in PALLET_ROOTS:
                result[frame]['pallet_bounds'][root] = pallet_bounds(scene, depsgraph, root)
    return result


def batch_rigid_meshes(scene):
    started = time.perf_counter()
    initial_frame = scene.frame_current
    original_targets, referenced_objects = constraint_targets(scene)
    tracked_names = [obj.name for obj in scene.objects
                     if obj.animation_data or obj.constraints or obj in referenced_objects]
    for name in PALLET_ROOTS:
        constraints = scene.objects[name].constraints
        assert any(constraint.type == 'COPY_TRANSFORMS' and constraint.target is not None
                   for constraint in constraints), f'Missing carried-load target: {name}'
    original_poses = pose_snapshot(scene, tracked_names)
    scene.frame_set(initial_frame)
    depsgraph = bpy.context.evaluated_depsgraph_get()
    candidates = []
    groups = defaultdict(list)
    for obj in list(scene.objects):
        eligible, anchor = group_anchor(obj, referenced_objects)
        if eligible:
            candidates.append((obj, anchor))
    report = {'objects_before': len(scene.objects), 'candidate_meshes': len(candidates)}
    print('BATCH_START', json.dumps(report), flush=True)
    # Snapshot evaluated geometry before linking any new objects; this avoids a
    # dependency-graph invalidation between thousands of identical evaluations.
    for index, (obj, anchor) in enumerate(candidates):
        evaluated = obj.evaluated_get(depsgraph)
        mesh = bpy.data.meshes.new_from_object(
            evaluated, preserve_all_data_layers=True, depsgraph=depsgraph)
        transform = obj.matrix_world.copy()
        if anchor:
            transform = anchor.matrix_world.inverted_safe() @ transform
        mesh.transform(transform)
        mesh.update()
        duplicate = bpy.data.objects.new('Render batch source / ' + obj.name, mesh)
        duplicate.parent = anchor
        signature = tuple(material.name if material else '' for material in mesh.materials)
        groups[(anchor, signature)].append(duplicate)
        if index % 1000 == 0:
            print('BAKED', index, 'of', len(candidates), flush=True)
    bpy.data.batch_remove(ids=tuple(obj for obj, anchor in candidates))
    for (anchor, signature), objects in groups.items():
        for obj in objects:
            scene.collection.objects.link(obj)
        if len(objects) > 1:
            with bpy.context.temp_override(
                    object=objects[0], active_object=objects[0],
                    selected_objects=objects, selected_editable_objects=objects):
                bpy.ops.object.join()
        objects[0].name = 'Render batch / ' + (anchor.name if anchor else 'Static warehouse') + ' / ' + ', '.join(signature)
    # Keep EVERY empty. Blender Object.users does not count all constraint
    # references, so treating childless empties as unused breaks carried loads.
    updated_targets, _ = constraint_targets(scene)
    assert updated_targets == original_targets, 'Constraint targets changed while batching'
    updated_poses = pose_snapshot(scene, tracked_names)
    matrix_error = 0.0
    bounds_error = 0.0
    comparisons = []
    for frame in POSE_FRAMES:
        for name in tracked_names:
            before = original_poses[frame]['matrices'][name]
            after = updated_poses[frame]['matrices'][name]
            matrix_error = max(matrix_error, max(abs(a - b) for a, b in zip(before, after)))
        for name in PALLET_ROOTS if frame in BOUNDS_FRAMES else []:
            before = original_poses[frame]['pallet_bounds'][name]
            after = updated_poses[frame]['pallet_bounds'][name]
            error = max(abs(a - b) for a, b in zip(before, after))
            bounds_error = max(bounds_error, error)
            comparisons.append({'frame': frame, 'pallet': name,
                                'master_bounds': before, 'render_copy_bounds': after,
                                'max_error_metres': error})
    assert matrix_error < 1e-5, f'Rig matrix mismatch: {matrix_error}'
    assert bounds_error < 1e-4, f'Pallet geometry mismatch: {bounds_error} metres'
    scene.frame_set(initial_frame)
    scene.render.use_persistent_data = True
    report.update({'groups': len(groups), 'removed_orphan_empties': 0,
                   'objects_after': len(scene.objects),
                   'constraint_targets': original_targets,
                   'tracked_rig_objects': len(tracked_names),
                   'pose_frames_verified': POSE_FRAMES,
                   'rig_validation': 'passed',
                   'max_rig_matrix_error': matrix_error,
                   'max_pallet_vertex_bounds_error_metres': bounds_error,
                   'pallet_pose_comparisons': comparisons,
                   'preparation_seconds': round(time.perf_counter() - started, 4)})
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--benchmark', type=int, nargs='*', default=[])
    parser.add_argument('--report', type=Path)
    args = parser.parse_args(sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else [])
    source = Path(bpy.data.filepath).resolve()
    destination = args.output.resolve()
    if destination == source:
        parser.error('The render copy must use a different path from the editable master')
    scene = bpy.context.scene
    report = {'source_master': str(source), 'render_copy': str(destination)}
    report.update(batch_rigid_meshes(scene))
    destination.parent.mkdir(parents=True, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=str(destination))
    report['benchmark_seconds'] = {}
    for frame in args.benchmark:
        scene.frame_set(frame)
        scene.render.filepath = str(destination.parent / f'optimized_preview_{frame:04d}.jpg')
        started = time.perf_counter()
        bpy.ops.render.render(write_still=True)
        elapsed = round(time.perf_counter() - started, 4)
        report['benchmark_seconds'][str(frame)] = elapsed
        print('BATCH_RENDER', frame, elapsed, flush=True)
    report_path = args.report or destination.with_suffix('.report.json')
    report_path.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print('BATCH_COMPLETE', json.dumps(report), flush=True)


if __name__ == '__main__':
    main()
