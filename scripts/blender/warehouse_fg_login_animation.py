"""Warehouse FG / PT. Yamatogomu Indonesia — editable 90-second Eevee film.

Blender 5.3:
  blender -b --python scripts/blender/warehouse_fg_login_animation.py -- --build
  blender -b artifacts/warehouse-login/warehouse_fg_login_master.blend -s 1 -e 2700 -a
  blender -b --python scripts/blender/warehouse_fg_login_animation.py -- --build --preview 1020

Every operation is keyframed in the saved scene. No video/image stand-ins are used
for the warehouse. Dimensions are metres. Original company logo is packed.
"""
import argparse
import math
import os
from pathlib import Path
import random
import sys
import time

import bpy
from mathutils import Vector

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'artifacts' / 'warehouse-login'
RENDER = Path(os.environ.get('LOCALAPPDATA', str(ROOT / 'storage'))) / 'Temp' / 'warehouse-fg-login-render'
random.seed(21)
BOX_MESHES = {}


def material(name, color, metallic=0, roughness=.5, emission=0):
    m = bpy.data.materials.new(name)
    m.diffuse_color = (*color, 1)
    m.use_nodes = True
    p = m.node_tree.nodes.get('Principled BSDF')
    p.inputs['Base Color'].default_value = (*color, 1)
    p.inputs['Metallic'].default_value = metallic
    p.inputs['Roughness'].default_value = roughness
    if emission:
        p.inputs['Emission Color'].default_value = (*color, 1)
        p.inputs['Emission Strength'].default_value = emission
    return m


def box(name, loc, scale, mat, bevel=0, parent=None):
    key = (tuple(scale), mat.name if mat else '')
    mesh = BOX_MESHES.get(key)
    if mesh is None:
        x, y, z = [v / 2 for v in scale]
        mesh = bpy.data.meshes.new(name + ' geometry')
        mesh.from_pydata([(-x,-y,-z),(-x,-y,z),(-x,y,-z),(-x,y,z),(x,-y,-z),(x,-y,z),(x,y,-z),(x,y,z)], [], [(2,6,4,0),(5,7,3,1),(4,5,1,0),(3,7,6,2),(1,3,2,0),(6,7,5,4)])
        if mat:
            mesh.materials.append(mat)
        BOX_MESHES[key] = mesh
    o = bpy.data.objects.new(name, mesh)
    bpy.context.collection.objects.link(o)
    o.location = loc
    if bevel:
        mod = o.modifiers.new('Manufactured edge radius', 'BEVEL')
        mod.width = bevel
        mod.segments = 2
        mod = o.modifiers.new('Weighted surface normals', 'WEIGHTED_NORMAL')
    if parent:
        o.parent = parent
    return o


def empty(name, loc=(0, 0, 0), parent=None):
    o = bpy.data.objects.new(name, None)
    bpy.context.collection.objects.link(o)
    o.location = loc
    o.parent = parent
    return o


def cylinder(name, loc, radius, depth, mat, rot=(0, 0, 0), parent=None, vertices=20):
    bpy.ops.mesh.primitive_cylinder_add(vertices=vertices, radius=radius, depth=depth, location=loc, rotation=rot)
    o = bpy.context.object
    o.name = name
    o.data.materials.append(mat)
    if parent:
        o.parent = parent
    for p in o.data.polygons:
        p.use_smooth = True
    return o


def beam(name, a, b, width, mat, parent=None):
    d = Vector(b) - Vector(a)
    o = box(name, (Vector(a) + Vector(b)) / 2, (width, width, d.length), mat, parent=parent)
    o.rotation_euler = d.to_track_quat('Z', 'Y').to_euler()
    return o


def text(name, value, loc, size, mat, rot=(math.pi / 2, 0, 0), parent=None, align='CENTER'):
    cu = bpy.data.curves.new(name, 'FONT')
    cu.body = value
    cu.align_x = align
    cu.align_y = 'CENTER'
    cu.size = size
    cu.extrude = .0005
    ob = bpy.data.objects.new(name, cu)
    bpy.context.collection.objects.link(ob)
    ob.location = loc
    ob.rotation_euler = rot
    ob.data.materials.append(mat)
    ob.parent = parent
    return ob


def keys(o, prop, values, interpolation='BEZIER'):
    for frame, value in values:
        setattr(o, prop, value)
        o.keyframe_insert(data_path=prop, frame=frame)
    # Keyframes are authored with eased, constant-velocity middles.
    if o.animation_data and o.animation_data.action:
        action = o.animation_data.action
        try:
            curves = action.fcurves
        except AttributeError:
            curves = [fc for layer in action.layers for strip in layer.strips for cb in strip.channelbags for fc in cb.fcurves]
        for fc in curves:
            if fc.data_path != prop:
                continue
            for k in fc.keyframe_points:
                k.interpolation = interpolation
                if interpolation == 'BEZIER':
                    k.handle_left_type = 'AUTO_CLAMPED'
                    k.handle_right_type = 'AUTO_CLAMPED'


def linked_copy(source, name, loc, parent=None):
    ob = source.copy()
    ob.data = source.data
    ob.name = name
    bpy.context.collection.objects.link(ob)
    ob.parent = parent
    ob.location = loc
    return ob


def make_pallet(name, loc, parent=None, loaded=True, labels=True):
    root = empty(name, loc, parent)
    for x in [-.57, 0, .57]:
        box(name + ' timber bearer', (x, 0, .08), (.13, 1.4, .16), WOOD, .012, root)
    for y in [-.6, -.3, 0, .3, .6]:
        box(name + ' deck slat', (0, y, .195), (1.38, .22, .07), WOOD, .008, root)
    if loaded:
        for layer in range(2):
            for x in [-.34, .34]:
                for y in [-.34, .34]:
                    carton(name + ' FG carton', (x, y, .23 + .34 + layer * .68), (.65, .65, .67), root, labels)
        # Recyclable restraint straps anchor the stack to the pallet.
        for x in [-.47, .47]:
            box(name + ' securing strap top', (x, 0, 1.575), (.025, 1.32, .008), STRAP, parent=root)
            for y in [-.666, .666]:
                box(name + ' securing strap', (x, y, .9), (.025, .008, 1.36), STRAP, parent=root)
    return root


def carton(name, loc, scale=(.65, .65, .67), parent=None, labels=True):
    root = empty(name, loc, parent)
    box(name + ' corrugated carton', (0, 0, 0), scale, CARDBOARD, .012, root)
    w, d, h = scale
    box('Kraft packing tape', (0, 0, h / 2 + .003), (.07, d, .006), TAPE, parent=root)
    if labels:
        box('Identification label', (.08, -d / 2 - .004, .02), (w * .53, .006, h * .38), PAPER, parent=root)
        # One linked barcode mesh; realistic varying bar widths, not flat gray blocks.
        vertices, faces = [], []
        for i, width in enumerate([.009, .005, .014, .007, .004, .011, .006, .014, .005, .008, .004, .013]):
            x = -.064 + i * .023
            base = len(vertices)
            vertices.extend([(x-width/2,-d/2-.009,-.04),(x+width/2,-d/2-.009,-.04),(x+width/2,-d/2-.009,.09),(x-width/2,-d/2-.009,.09)])
            faces.append((base,base+1,base+2,base+3))
        mesh = bpy.data.meshes.get('Barcode geometry '+str(d))
        if mesh is None:
            mesh=bpy.data.meshes.new('Barcode geometry '+str(d))
            mesh.from_pydata(vertices,[],faces)
            mesh.materials.append(INK)
        bars=bpy.data.objects.new('Printed barcode',mesh)
        bpy.context.collection.objects.link(bars)
        bars.parent=root
        box('Print line', (.055, -d / 2 - .009, -.065), (.23, .005, .01), INK, parent=root)
    return root


def rack(x, side):
    # Continuous longitudinal rack. The two empty slots have real moving pallets.
    ys = [-7, -3, 1, 5, 9]
    for y in [-9, -5, -1, 3, 7, 11]:
        for xx in [x - .93, x + .93]:
            box('Rack perforated blue upright', (xx, y, 3.3), (.12, .14, 6.6), RACK, .01)
            box('Rack bolted footplate', (xx, y, .055), (.28, .3, .11), STEEL)
        for z in [1, 3, 5]:
            beam('Rack diagonal X brace', (x - .91, y, z - .8), (x + .91, y, z + .8), .035, STEEL)
        for xx in [x - .98, x + .98]:
            box('Upright safety guard', (xx, y - .04, .36), (.2, .28, .72), YELLOW, .025)
    for bay, y in enumerate(ys):
        for level, z in enumerate([.26, 2.45, 4.65]):
            for xx in [x - .91, x + .91]:
                box('Orange load beam', (xx, y, z), (.11, 3.86, .18), ORANGE, .012)
            for yy in [y - .95, y + .95]:
                box('Galvanized load crossmember', (x, yy, z + .05), (1.8, .06, .08), STEEL)
                # Pallet axis along row: two pallet positions per bay.
                omit = (x == 4 and bay == 1 and level == 1 and yy == y - .95) or (x == -4 and bay == 3 and level == 0 and yy == y - .95)
                if not omit:
                    make_pallet('Stored %s-%02d-%d' % (side, bay + 1, level + 1), (x, yy, z + .09), labels=(level < 2))
            # Readable location signs only near aisle, not tiny narrative text.
            box('Location plate', (x - 1.01 if x > 0 else x + 1.01, y, z + .05), (.015, .63, .23), PAPER)
    # Large aisle sign faces the arriving operator.
    box('Aisle header', (x, -9.08, 6.2), (1.7, .08, .55), RACK, .02)
    text('Aisle code', side, (x, -9.13, 6.2), .35, PAPER)


def forklift(name, loc, bodymat):
    root = empty(name + ' | vehicle motion', loc)
    box('Counterweight cast chassis', (0, -.18, .74), (1.32, 2.12, .63), bodymat, .16, root)
    box('Rear radiator grille', (0, -1.26, .78), (.92, .025, .32), INK, .03, root)
    for x in [-.35, -.21, -.07, .07, .21, .35]:
        box('Radiator louvre', (x, -1.28, .78), (.035, .02, .26), STEEL, parent=root)
    box('Operator foot well', (0, .28, 1.04), (1.03, 1.14, .1), INK, .03, root)
    box('Padded operator seat', (0, -.23, 1.34), (.58, .57, .16), RUBBER, .09, root)
    box('Seat backrest', (0, -.5, 1.63), (.58, .13, .55), RUBBER, .08, root)
    beam('Steering column', (0, .47, 1.1), (0, .35, 1.6), .055, INK, root)
    wheel = cylinder('Steering wheel', (0, .35, 1.63), .2, .035, RUBBER, (.2, 0, 0), root)
    for x in [-.57, .57]:
        for y in [-.8, .65]:
            beam('Overhead guard post', (x, y, 1), (x, y, 2.48), .07, RACK, root)
    box('Overhead safety roof', (0, -.04, 2.48), (1.32, 1.8, .08), RACK, .04, root)
    for y in [-.65, -.35, -.05, .25, .55]:
        box('Roof protective louvre', (0, y, 2.54), (1.22, .08, .04), STEEL, parent=root)
    wheels = []
    for x in [-.69, .69]:
        for y, radius in [(-.78, .3), (.62, .37)]:
            w = cylinder('Solid forklift tyre', (x, y, radius), radius, .24, RUBBER, (0, math.pi / 2, 0), root)
            wheels.append(w)
            cylinder('Steel wheel hub', (x + (-.13 if x < 0 else .13), y, radius), radius * .5, .02, STEEL, (0, math.pi / 2, 0), root)
    for x in [-.45, .45]:
        box('Outer mast channel', (x, .95, 1.52), (.13, .19, 2.95), INK, .025, root)
        cylinder('Hydraulic lift ram', (x * .55, 1.01, 1.16), .045, 2.1, STEEL, parent=root)
    box('Mast top crossmember', (0, .95, 2.94), (1.03, .18, .14), INK, .02, root)
    carrier = empty(name + ' | hydraulic carriage', (0, 0, .16), root)
    for x in [-.38, .38]:
        box('Telescoping inner mast channel', (x, .96, .8), (.08, .12, 1.65), STEEL, .012, carrier)
        cylinder('Extending hydraulic chrome rod', (x * .65, 1.01, .8), .025, 1.6, STEEL, parent=carrier)
    for x in [-.37, .37]:
        box('Carriage vertical', (x, 1.09, .43), (.1, .12, .88), STEEL, .015, carrier)
        box('Forged load fork', (x, 1.95, .045), (.12, 1.9, .09), STEEL, .016, carrier)
    box('Load backrest', (0, 1.12, .65), (1.06, .07, .07), STEEL, parent=carrier)
    for x in [-.51, -.25, 0, .25, .51]:
        box('Load backrest grille', (x, 1.12, .43), (.025, .04, .72), STEEL, parent=carrier)
    beacon = cylinder('Amber rotating warning beacon', (0, -.55, 2.67), .1, .19, AMBER, parent=root)
    spotdata = bpy.data.lights.new('Low intensity amber beacon', 'POINT')
    spotdata.energy = 7
    spotdata.color = (1, .35, .035)
    spotdata.shadow_soft_size = .15
    spotdata.use_shadow = False
    spot = bpy.data.objects.new('Amber beacon light', spotdata)
    bpy.context.collection.objects.link(spot)
    spot.parent = root
    spot.location = (0, -.55, 2.8)
    for x in [-.46, .46]:
        box('LED headlamp', (x, .86, 1.12), (.18, .04, .1), LED, .025, root)
    root['wheel_names'] = '|'.join(w.name for w in wheels)
    root['equipment_type'] = 'Counterbalanced electric forklift; 1.5 tonne class'
    return root, carrier


def wheel_motion(root, distance_keys):
    for name in root['wheel_names'].split('|'):
        w = bpy.data.objects[name]
        radius = .37 if w.location.y > 0 else .3
        spin=empty('Fixed axle / rotating wheel',tuple(w.location),root)
        w.parent=spin
        w.location=(0,0,0)
        keys(spin, 'rotation_euler', [(f, (distance / radius, 0, 0)) for f, distance in distance_keys], 'LINEAR')


def carried_pallet(pallet, carriage, first, last):
    # Tines enter between the timber bearers and contact the deck underside,
    # .16m above the pallet origin; fork upper surface is carriage + .09m.
    helper = empty('Physical load support / ' + pallet.name, (0, 2.1, -.07), carriage)
    c = pallet.constraints.new('COPY_TRANSFORMS')
    c.name = 'Follow actual fork carriage only while carried'
    c.target = helper
    for f, strength in [(1, 1 if first == 1 else 0), (max(1, first - 1), 0 if first > 1 else 1), (first, 1), (last, 1), (last + 1, 0), (2700, 0)]:
        c.influence = strength
        c.keyframe_insert(data_path='influence', frame=f)
    action = pallet.animation_data.action
    for layer in action.layers:
        for strip in layer.strips:
            for cb in strip.channelbags:
                for fc in cb.fcurves:
                    if 'constraints' in fc.data_path:
                        for k in fc.keyframe_points:
                            k.interpolation = 'CONSTANT'


def bake_ramp_contact(root, start=1920, end=2220):
    """Bake both tyre axles tangent to the floor, ramp and trailer deck.

    Solve the rigid body's pitch and height from two circular tyre contacts;
    this also handles the frames where the axles straddle a ramp boundary.
    Preserve the authored horizontal route, yaw and wheel-spin animation.
    """
    scene = bpy.context.scene
    original_frame = scene.frame_current
    poses = []
    for frame in range(start, end + 1):
        scene.frame_set(frame)
        poses.append((frame, root.location.copy(), root.rotation_euler.copy()))
    circle = [(math.cos(i * 2 * math.pi / 128),
               math.sin(i * 2 * math.pi / 128)) for i in range(128)]
    axle_points = {
        'rear': [(-.78 + .3 * c, .3 + .3 * t) for c, t in circle],
        'front': [(.62 + .37 * c, .37 + .37 * t) for c, t in circle],
    }

    def required_height(body_y, pitch, points):
        cosine, sine = math.cos(pitch), math.sin(pitch)
        required = -math.inf
        for local_y, local_z in points:
            world_y = body_y + cosine * local_y - sine * local_z
            relative_z = sine * local_y + cosine * local_z
            terrain_z = max(0.0, min(1.02, (world_y - 11) * .204))
            required = max(required, terrain_z - relative_z)
        return required

    for frame, location, rotation in poses:
        low, high = 0.0, math.atan(.204)
        for _ in range(32):
            pitch = (low + high) / 2
            rear = required_height(location.y, pitch, axle_points['rear'])
            front = required_height(location.y, pitch, axle_points['front'])
            if front > rear:
                low = pitch
            else:
                high = pitch
        pitch = (low + high) / 2
        height = max(required_height(location.y, pitch, axle_points['rear']),
                     required_height(location.y, pitch, axle_points['front']))
        location.z = height
        rotation.x = pitch
        root.location = location
        root.rotation_euler = rotation
        root.keyframe_insert(data_path='location', index=2, frame=frame)
        root.keyframe_insert(data_path='rotation_euler', index=0, frame=frame)
    action = root.animation_data.action
    try:
        curves = action.fcurves
    except AttributeError:
        curves = [fc for layer in action.layers for strip in layer.strips
                  for channelbag in strip.channelbags for fc in channelbag.fcurves]
    for curve in curves:
        if (curve.data_path, curve.array_index) in [('location', 2), ('rotation_euler', 0)]:
            for key in curve.keyframe_points:
                key.interpolation = 'LINEAR'
    root['ramp_contact'] = 'Two axle circular contact, 128 samples, per-frame pitch/height bake'
    scene.frame_set(original_frame)


def area(name, loc, energy, color, size, target):
    d = bpy.data.lights.new(name, 'AREA')
    d.energy = energy
    d.color = color
    d.shape = 'DISK'
    d.size = size
    d.shadow_maximum_resolution = .035
    if 'skylight' in name or 'fill' in name:
        d.use_shadow = False
    o = bpy.data.objects.new(name, d)
    bpy.context.collection.objects.link(o)
    o.location = loc
    o.rotation_euler = (Vector(target) - o.location).to_track_quat('-Z', 'Y').to_euler()
    return o


def camera(name, start, end, target_start, target_end, first, last, lens=38):
    d = bpy.data.cameras.new(name)
    d.lens = lens
    d.clip_start = .1
    d.clip_end = 1200
    o = bpy.data.objects.new(name, d)
    bpy.context.collection.objects.link(o)
    keys(o, 'location', [(first, start), (last, end)], 'LINEAR')
    rotations = []
    for f, pos, target in [(first, start, target_start), (last, end, target_end)]:
        rotations.append((f, (Vector(target) - Vector(pos)).to_track_quat('-Z', 'Y').to_euler()))
    keys(o, 'rotation_euler', rotations, 'LINEAR')
    m = bpy.context.scene.timeline_markers.new(name, frame=first)
    m.camera = o
    return o


def branding():
    # A separate physical black studio at x=1000 avoids warehouse visibility.
    black = material('Branding studio black', (0, 0, 0), roughness=1)
    bp=black.node_tree.nodes.get('Principled BSDF')
    black.node_tree.nodes.remove(bp)
    be=black.node_tree.nodes.new('ShaderNodeEmission')
    be.inputs['Color'].default_value=(0,0,0,1)
    black.node_tree.links.new(be.outputs[0],black.node_tree.nodes.get('Material Output').inputs[0])
    white = material('Corporate white typography', (1, 1, 1), emission=1)
    logo_mat = bpy.data.materials.new('Original company mark | white on black material')
    logo_mat.use_nodes = True
    n = logo_mat.node_tree.nodes
    n.clear()
    uv = n.new('ShaderNodeTexImage')
    uv.image = bpy.data.images.load(str(ROOT / 'public' / 'logo.png'))
    uv.image.pack()
    separate = n.new('ShaderNodeSeparateColor')
    logo_mat.node_tree.links.new(uv.outputs['Color'], separate.inputs[0])
    # Transparent white paper; inverted luminance retains original contour.
    bw = n.new('ShaderNodeRGBToBW')
    logo_mat.node_tree.links.new(uv.outputs['Color'], bw.inputs[0])
    ramp = n.new('ShaderNodeValToRGB')
    ramp.color_ramp.elements[0].position = .15
    ramp.color_ramp.elements[0].color = (1, 1, 1, 1)
    ramp.color_ramp.elements[1].position = .76
    ramp.color_ramp.elements[1].color = (0, 0, 0, 1)
    logo_mat.node_tree.links.new(bw.outputs[0], ramp.inputs[0])
    em = n.new('ShaderNodeEmission')
    em.inputs['Color'].default_value = (1, 1, 1, 1)
    em.inputs['Strength'].default_value = 1
    transparent = n.new('ShaderNodeBsdfTransparent')
    mix = n.new('ShaderNodeMixShader')
    alpha=n.new('ShaderNodeMath')
    alpha.operation='MULTIPLY'
    logo_mat.node_tree.links.new(ramp.outputs[0],alpha.inputs[0])
    logo_mat.node_tree.links.new(uv.outputs['Alpha'],alpha.inputs[1])
    logo_mat.node_tree.links.new(alpha.outputs[0], mix.inputs[0])
    logo_mat.node_tree.links.new(transparent.outputs[0], mix.inputs[1])
    logo_mat.node_tree.links.new(em.outputs[0], mix.inputs[2])
    out = n.new('ShaderNodeOutputMaterial')
    logo_mat.node_tree.links.new(mix.outputs[0], out.inputs['Surface'])
    logo_mat.surface_render_method = 'DITHERED'
    box('Black corporate studio', (1000, 1, 2), (24, .05, 15), black)
    # Image texture plane preserves the official raster's original proportions.
    bpy.ops.mesh.primitive_plane_add(size=2, location=(1000, -.02, 3.24), rotation=(math.pi / 2, 0, 0))
    ob = bpy.context.object
    ob.name = 'Original PT Yamatogomu mark (packed replaceable image)'
    ob.scale = (1.15, .8625, 1)
    ob.data.materials.append(logo_mat)
    text('Brand title', 'Warehouse FG', (1000, -.03, 1.78), .54, white)
    text('Brand subtitle', 'Sistem Manajemen Inventori & Pengiriman PT. Yamatogomu Indonesia', (1000, -.03, 1.04), .158, white)
    cam = camera('07 | Corporate branding on black', (1000, -11, 2.3), (1000, -11, 2.3), (1000, 0, 2.3), (1000, 0, 2.3), 2371, 2700, 42)
    return cam


def build():
    global WOOD, CARDBOARD, TAPE, PAPER, INK, STRAP, RACK, STEEL, ORANGE, YELLOW, RUBBER, LED, AMBER
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete(use_global=False)
    s = bpy.context.scene
    s.name = 'Warehouse FG | 90 seconds | 1080p 30fps'
    s.render.engine = 'BLENDER_EEVEE'
    s.eevee.taa_render_samples = 16
    s.eevee.use_raytracing = False
    s.eevee.use_shadows = True
    s.eevee.shadow_ray_count = 1
    s.eevee.shadow_step_count = 4
    s.eevee.shadow_pool_size = '1024'
    s.eevee.shadow_resolution_scale = .5
    s.render.resolution_x = 1920
    s.render.resolution_y = 1080
    s.render.resolution_percentage = 100
    s.render.fps = 30
    s.frame_start = 1
    s.frame_end = 2700
    s.render.image_settings.file_format = 'JPEG'
    s.render.image_settings.quality = 95
    RENDER.mkdir(parents=True, exist_ok=True)
    s.render.filepath = str(RENDER / 'frame_')
    s.render.film_transparent = False
    s.view_settings.view_transform = 'AgX'
    s.view_settings.look = 'AgX - Medium High Contrast'
    s.view_settings.exposure = -.45
    s.world.color = (.14, .17, .2)
    s.world.use_nodes = True
    s.world.node_tree.nodes.get('Background').inputs['Color'].default_value = (.23, .3, .39, 1)
    s.world.node_tree.nodes.get('Background').inputs['Strength'].default_value = .45
    concrete = material('Polished concrete | subtle aggregate', (.31, .35, .38), .12, .53)
    nodes = concrete.node_tree.nodes
    noise = nodes.new('ShaderNodeTexNoise')
    noise.inputs['Scale'].default_value = 115
    noise.inputs['Detail'].default_value = 2
    bump = nodes.new('ShaderNodeBump')
    bump.inputs['Strength'].default_value = .13
    bump.inputs['Distance'].default_value = .018
    concrete.node_tree.links.new(noise.outputs['Fac'], bump.inputs['Height'])
    concrete.node_tree.links.new(bump.outputs[0], nodes.get('Principled BSDF').inputs['Normal'])
    wall = material('Pale insulated metal wall', (.55, .61, .65), .35, .6)
    RACK = material('Japanese industrial blue steel', (.035, .13, .2), .65, .35)
    STEEL = material('Brushed galvanized steel', (.39, .46, .51), .8, .32)
    ORANGE = material('Rack beam safety orange', (.66, .21, .045), .4, .4)
    YELLOW = material('Safety yellow powder coat', (.86, .58, .07), .28, .4)
    WOOD = material('Seasoned pallet timber', (.36, .22, .105), roughness=.82)
    CARDBOARD = material('Natural corrugated kraft cardboard', (.52, .35, .19), roughness=.85)
    TAPE = material('Paper carton sealing tape', (.67, .48, .27), roughness=.6)
    PAPER = material('White labels', (.87, .89, .87), roughness=.78)
    INK = material('Printed charcoal and chassis', (.012, .02, .025), .12, .52)
    STRAP = material('Charcoal polypropylene pallet straps', (.055, .068, .07), roughness=.7)
    RUBBER = material('Solid textured rubber', (.025, .031, .036), roughness=.86)
    LED = material('Warm industrial LED', (1, .87, .65), emission=3)
    AMBER = material('Amber warning light', (1, .35, .035), emission=2)
    teal = material('WHFG corporate teal', (.025, .29, .3), .45, .32)
    green = material('Verified scanner green', (.06, .9, .42), emission=2)
    screen = material('Inventory monitor glass', (.025, .13, .17), .35, .28, .45)

    box('Warehouse polished concrete slab', (0, 3, -.16), (35, 43, .3), concrete)
    for x in [-17, 17]:
        box('Insulated side wall', (x, 3, 4.8), (.18, 42, 9.6), wall)
        for y in range(-16, 24, 3):
            box('Building steel column', (x - .22 if x > 0 else x + .22, y, 4.7), (.24, .24, 9.4), RACK)
    # Northern dock wall has an actual central opening, never a truck intersection.
    box('Dock wall left', (-10.8, 16, 4), (12.4, .25, 8), wall)
    box('Dock wall right', (10.8, 16, 4), (12.4, .25, 8), wall)
    box('Dock lintel', (0, 16, 7), (9.2, .25, 2), wall)
    box('Warehouse roof', (0, 3, 9.8), (34, 42, .12), wall)
    for y in range(-14, 25, 5):
        beam('Roof cross truss bottom chord', (-16.7, y, 8.2), (16.7, y, 8.2), .13, STEEL)
        for x in range(-16, 16, 4):
            beam('Roof triangular steel strut', (x, y, 8.2), (x + 2, y, 9.6), .075, STEEL)
            beam('Roof triangular steel strut', (x + 2, y, 9.6), (x + 4, y, 8.2), .075, STEEL)
        for x in [-11, -3, 5, 13]:
            box('Suspended linear luminaire casing', (x, y, 8.06), (2.4, .32, .1), INK, .03)
            box('Suspended linear LED diffuser', (x, y, 7.99), (2.24, .23, .025), LED)
    for x in [-10, -4, 4, 10]:
        rack(x, ('A' if x < 0 else 'B') + str(abs(x)))
    # Aisle boundaries and staging rectangles are physical paint films on floor.
    for x in [-2, 2]:
        box('Aisle safety line', (x, 2, .006), (.07, 24, .009), YELLOW)
    for x in [-7, -3, 3, 7]:
        for y in [12, 14.5]:
            box('Outbound staging bay border', (x, y, .008), (3.1, .06, .01), PAPER)
        for xx in [x - 1.55, x + 1.55]:
            box('Staging bay side border', (xx, 13.25, .008), (.06, 2.55, .01), PAPER)
    for y in [-10, 12]:
        for x in [-14, -12, -10, -8, -6, -4, 4, 6, 8, 10, 12, 14]:
            stripe = box('Pedestrian crossing', (x, y, .009), (.7, 1.2, .012), YELLOW)
    # Low impact barriers isolate conveyor work area.
    for x in [-15, -4.8]:
        for y in [-14.3, -10.5]:
            cylinder('Barrier upright', (x, y, .6), .055, 1.2, YELLOW)
        beam('Safety handrail', (x, -14.3, 1.03), (x, -10.5, 1.03), .07, YELLOW)
    box('Inbound zone signage', (-10, -9.8, 3.6), (5, .07, .7), RACK, .025)
    text('Inbound zone title', '01 / INBOUND', (-10, -9.85, 3.6), .4, PAPER)
    box('Outbound dock signage', (0, 15.82, 6.35), (6, .08, .65), RACK)
    text('Outbound zone title', '03 / DISPATCH', (0, 15.76, 6.35), .43, PAPER)

    # Conveyor is a real roller line with animated cartons and scanner portal.
    for y in [-12.65, -11.35]:
        box('Conveyor side rail', (-10, y, .96), (9.8, .12, .2), STEEL, .025)
    for x in [-14.5, -12, -9.5, -7, -5.5]:
        for y in [-12.5, -11.5]:
            box('Conveyor adjustable leg', (x, y, .45), (.09, .09, .9), STEEL)
            box('Conveyor floor foot', (x, y, .035), (.3, .3, .07), INK)
    for i in range(49):
        x = -14.8 + i * .2
        roller = cylinder('Powered galvanized conveyor roller', (x, -12, 1.02), .065, 1.2, STEEL, (math.pi / 2, 0, 0), vertices=12)
        keys(roller, 'rotation_euler', [(301, (math.pi / 2, 0, 0)), (750, (math.pi / 2, 18, 0))], 'LINEAR')
    for i in range(5):
        c = carton('Inbound conveyor carton %d' % i, (-14.3 + i * 1.25, -12, 1.44), (.8, .8, .7), labels=True)
        keys(c, 'location', [(1, (-14.3 + i * 1.25, -12, 1.44)), (301, (-14.3 + i * 1.25, -12, 1.44)), (750, (-10.5 + i * 1.25, -12, 1.44)), (2700, (-10.5 + i * 1.25, -12, 1.44))], 'LINEAR')
    for y in [-12.85, -11.15]:
        box('Scanner portal leg', (-9, y, 1.4), (.15, .15, 2.8), RACK, .025)
    box('Scanner portal arch', (-9, -12, 2.8), (.18, 1.85, .18), RACK, .025)
    box('Barcode imaging head', (-9, -12, 2.61), (.24, .45, .19), INK, .04)
    box('Barcode scanning strip', (-9, -12, 2.5), (.026, .7, .012), green)
    cylinder('Scanner confirmed status lamp', (-9, -11.15, 3.02), .06, .17, green)
    emission = green.node_tree.nodes.get('Principled BSDF').inputs['Emission Strength']
    for f, power in [(1, .25), (301,.25), (335,1.8), (370,.25), (480,1.8), (520,.25), (630,1.8), (670,.25), (750,.25), (1720,1.8), (1800,.25), (2700,.25)]:
        emission.default_value = power
        emission.keyframe_insert(data_path='default_value', frame=f)
    box('Inventory workstation stand', (-7, -10, .62), (.08, .08, 1.24), STEEL)
    box('Inventory monitor casing', (-7, -10, 1.65), (1.25, .09, .73), INK, .06)
    box('Inventory screen', (-7, -10.055, 1.65), (1.12, .015, .6), screen, .025)
    for z in [1.5, 1.63, 1.76]:
        box('Verified inventory screen row', (-7.14, -10.066, z), (.65, .005, .035), green)
    make_pallet('Inbound pallet preparation', (-5.7, -9.6, 0))
    # Two fully articulated electric forklifts, loads animated independently so
    # transfers really leave the rack / forks instead of following forever.
    f1, carrier1 = forklift('Forklift 01 / storage', (0, -11, 0), YELLOW)
    keys(f1, 'location', [(1, (0, -11, 0)), (750, (0, -11, 0)), (930, (0, -3.95, 0)), (1020, (0, -3.95, 0)), (1140, (1.9, -3.95, 0)), (1260, (1.9, -3.95, 0)), (1350, (0, -3.95, 0)), (1500, (0, -3.95, 0)), (1800, (0, -7, 0)), (2700, (0, -7, 0))])
    keys(f1, 'rotation_euler', [(1, (0, 0, 0)), (930, (0, 0, 0)), (1020, (0, 0, -math.pi / 2)), (1350, (0, 0, -math.pi / 2)), (1500, (0, 0, -math.pi)), (2700, (0, 0, -math.pi))])
    keys(carrier1, 'location', [(1, (0, 0, .32)), (1020, (0, 0, .32)), (1110, (0, 0, 2.76)), (1200, (0, 0, 2.76)), (1230, (0, 0, 2.61)), (1350, (0, 0, 2.61)), (1440, (0, 0, .16)), (2700, (0, 0, .16))])
    p1 = make_pallet('Inbound pallet to rack B4-02-L2', (4, -3.95, 2.54))
    p1.rotation_euler.z = -math.pi / 2
    carried_pallet(p1, carrier1, 1, 1230)
    wheel_motion(f1, [(1,0),(750,0),(930,7.05),(1020,7.05),(1140,8.95),(1260,8.95),(1350,7.05),(1500,7.05),(1800,10.1),(2700,10.1)])

    f2, carrier2 = forklift('Forklift 02 / picking and dispatch', (-1.9, 4.05, 0), teal)
    keys(f2, 'location', [(1, (-1.9, 4.05, 0)), (1351, (-1.9, 4.05, 0)), (1440, (-1.9, 4.05, 0)), (1530, (0, 4.05, 0)), (1620, (0, 4.05, 0)), (1800, (0, 10.2, 0)), (1920, (0, 10.2, 0)), (1965, (0, 11, 0)), (2180,(0,16,1.02)), (2220, (0, 17, 1.02)), (2370, (0, 17, 1.02)), (2700, (0, 17, 1.02))], 'LINEAR')
    keys(f2, 'rotation_euler', [(1, (0, 0, math.pi / 2)), (1530, (0, 0, math.pi / 2)), (1620, (0, 0, 0)), (1920,(0,0,0)),(1995,(.201,0,0)),(2150,(.201,0,0)),(2220,(0,0,0)),(2700, (0, 0, 0))])
    keys(carrier2, 'location', [(1, (0, 0, .42)), (1370, (0, 0, .42)), (1440, (0, 0, .56)), (1800, (0, 0, .56)), (1920, (0, 0, .32)), (2220, (0, 0, .32)), (2280, (0, 0, .07)), (2700, (0, 0, .07))])
    p2 = make_pallet('Picked pallet to truck dock 01', (-4, 4.05, .35))
    keys(p2, 'location', [(1, (-4,4.05,.35)),(1370,(-4,4.05,.35)),(2280,(0,19.1,1.02)),(2700,(0,19.1,1.02))])
    keys(p2, 'rotation_euler', [(1,(0,0,math.pi/2)),(1370,(0,0,math.pi/2)),(2280,(0,0,0)),(2700,(0,0,0))])
    carried_pallet(p2, carrier2, 1371, 2280)
    wheel_motion(f2, [(1,0),(1440,0),(1530,-2.2),(1620,-2.2),(1800,3.95),(1920,3.95),(2220,10.83),(2700,10.83)])
    bake_ramp_contact(f2)
    # Verification portal over dispatch aisle: real clearance above both loads.
    for x in [-1.65, 1.65]:
        box('Outbound verification portal', (x, 12, 1.85), (.12, .12, 3.7), RACK)
    box('Outbound barcode bridge', (0, 12, 3.7), (3.4, .2, .14), RACK)
    box('Outbound reader head', (0, 12, 3.53), (.3, .3, .2), INK)
    cylinder('Outbound verified lamp', (1.65, 12, 3.9), .075, .22, green)
    for x in [-7, -3, 3, 7]:
        make_pallet('Dispatch staged verified pallet', (x, 13.25, .0))
    # Articulated delivery truck beyond the open loading doorway.
    box('Truck chassis', (0, 21.5, .68), (2.6, 10.2, .3), INK, .08)
    box('Truck cargo deck', (0, 20.65, .94), (3.3, 9.7, .16), STEEL, .04)
    for x in [-1.68, 1.68]:
        box('Truck cargo side panel', (x, 20.8, 2.7), (.12, 9.8, 3.5), wall, .03)
        for y in [16.4, 18.4, 20.4, 22.4, 24.4]:
            box('Truck side ribs', (x * 1.015, y, 2.7), (.05, .09, 3.3), STEEL)
    box('Truck cargo roof', (0, 20.8, 4.5), (3.48, 9.9, .14), wall)
    box('Truck cargo front bulkhead', (0, 25.72, 2.7), (3.4, .12, 3.5), wall)
    box('Truck driver cab', (0, 27, 1.8), (2.7, 2.3, 2.5), teal, .18)
    box('Truck windscreen', (0, 28.16, 2.25), (2.3, .03, 1.0), screen, .08)
    for y in [17.5, 23.5, 27]:
        for x in [-1.55, 1.55]:
            cylinder('Truck pneumatic tyre', (x, y, .61), .61, .36, RUBBER, (0, math.pi / 2, 0))
    # Smooth leveler bridges warehouse and trailer; no float at the loading dock.
    ramp=bpy.data.meshes.new('Dock leveller sloped deck geometry')
    ramp.from_pydata([(-1.7,11,0),(1.7,11,0),(-1.7,16,0),(1.7,16,0),(-1.7,16,1.02),(1.7,16,1.02)],[],[(0,1,5,4),(0,4,2),(1,3,5),(2,4,5,3),(0,2,3,1)])
    ramp.materials.append(STEEL)
    ro=bpy.data.objects.new('Dock leveller | gradual five metre ramp',ramp)
    bpy.context.collection.objects.link(ro)
    for x in [-2.1, 2.1]:
        box('Dock rubber impact buffer', (x, 15.78, .8), (.32, .35, 1.4), RUBBER, .04)
    # Film lighting: large cool skylight + broad warm ceiling pools.
    area('Soft northern skylight', (0, 2, 9.3), 4800, (.64, .79, 1), 17, (0, 1, 0))
    area('Inbound warm softbox', (-10, -11, 7.6), 1800, (1, .82, .63), 7, (-10, -12, 0))
    area('Storage aisle softbox', (0, -3, 7.7), 2300, (.8, .89, 1), 7, (0, -3, 0))
    area('Outbound softbox', (0, 12, 7.4), 2300, (1, .86, .69), 7, (0, 14, 0))
    area('Dock daylight', (0, 20, 7), 2600, (.67, .81, 1), 8, (0, 11, 0))
    area('Camera side fill', (3, -17, 6), 1800, (.75, .86, 1), 10, (0, -1, 2))

    s.camera = camera('01 | Warehouse introduction', (10, -21, 6.5), (8, -18, 6), (0, 1, 2), (0, 1, 2.5), 1, 300, 30)
    camera('02 | Inbound scan and conveyor', (-11.5, -18, 4.5), (-8, -17.2, 3.5), (-9.8, -11.8, 1.5), (-8.5, -12, 1.5), 301, 750, 37)
    camera('03 | Pallet transport and storage', (-1.5, -12.8, 4), (-1, -10.5, 4.3), (1.3, -4, 2), (2.3, -3.95, 2.15), 751, 1350, 30)
    camera('04 | Picking and barcode verification', (2.4, -5, 3.6), (2.2, -.5, 3.5), (-2, 4.2, 1.6), (0, 9.5, 1.7), 1351, 1800, 30)
    camera('05 | Dispatch and delivery truck', (2.2, 3.4, 5.2), (1.9, 6, 4.5), (0, 15.3, 1.8), (0, 17, 1.8), 1801, 2370, 32)
    branding()
    # Blender 5 compositor group output. Fade affects warehouse/branding equally
    # and guarantees mathematically black frames 1,2370,2371,2700.
    tree = bpy.data.node_groups.new('Corporate fade to black | exact 90-second loop', 'CompositorNodeTree')
    tree.interface.new_socket(name='Image', in_out='OUTPUT', socket_type='NodeSocketColor')
    src = tree.nodes.new('CompositorNodeRLayers')
    src.scene = s
    mix = tree.nodes.new('ShaderNodeMix')
    mix.data_type = 'RGBA'
    mix.blend_type = 'MULTIPLY'
    mix.inputs[0].default_value = 1
    tree.links.new(src.outputs['Image'], mix.inputs[6])
    value = tree.nodes.new('ShaderNodeValue')
    value.name = 'Fade level | storyboard time boundaries'
    tree.links.new(value.outputs[0], mix.inputs[7])
    for f, level in [(1, 0), (100, 1), (2250, 1), (2370, 0), (2371, 0), (2430, 1), (2550, 1), (2700, 0)]:
        value.outputs[0].default_value = level
        value.outputs[0].keyframe_insert(data_path='default_value', frame=f)
    out = tree.nodes.new('NodeGroupOutput')
    tree.links.new(mix.outputs[2], out.inputs['Image'])
    s.compositing_node_group = tree
    s['project'] = 'Warehouse FG — PT. Yamatogomu Indonesia'
    s['storyboard_seconds'] = '0–10 Introduction;10–25 Inbound;25–45 Storage;45–60 Picking;60–75 Dispatch;75–79 Fade;79–85 Branding;85–90 Fade'
    s['duration_seconds'] = 90
    s['video_delivery'] = '1920x1080, 30fps, exactly 2700 frames, silent H.264 MP4 encoded from JPEG frame sequence'
    s['logo_source'] = 'Original repository public/logo.png; packed; rendered as white mark on black'
    s['render_directory'] = str(RENDER)
    # Human-readable project notes inside the .blend.
    notes = bpy.data.texts.new('README — Warehouse FG editable scene')
    notes.write(__doc__ + '\n\nAll vehicle, fork-carriage, pallet, camera and fade animation is keyed.\nStoryboard markers bind five cinematic cameras plus the black branding studio.\nOriginal repository logo is packed; replace the Image Texture in its named material.\nUse compositor enabled when rendering to retain the black fade sequence.\n')
    s.frame_set(180)
    OUT.mkdir(parents=True, exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=str(OUT / 'warehouse_fg_login_master.blend'))
    print('WAREHOUSE_SCENE_SAVED', OUT / 'warehouse_fg_login_master.blend', flush=True)
    return s


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--build', action='store_true')
    p.add_argument('--preview', type=int)
    p.add_argument('--render', action='store_true')
    p.add_argument('--start', type=int, default=1)
    p.add_argument('--end', type=int, default=2700)
    p.add_argument('--samples', type=int, default=16)
    args = p.parse_args(sys.argv[sys.argv.index('--') + 1:] if '--' in sys.argv else [])
    s = build() if args.build else bpy.context.scene
    s.eevee.taa_render_samples = args.samples
    if args.preview:
        s.frame_set(args.preview)
        s.render.filepath = str(RENDER / ('preview_%04d.jpg' % args.preview))
        t = time.monotonic()
        bpy.ops.render.render(write_still=True)
        print('WAREHOUSE_PREVIEW', s.render.filepath, 'seconds', time.monotonic() - t, flush=True)
    if args.render:
        s.frame_start = args.start
        s.frame_end = args.end
        s.render.filepath = str(RENDER / 'frame_')
        bpy.ops.render.render(animation=True)


if __name__ == '__main__':
    main()
