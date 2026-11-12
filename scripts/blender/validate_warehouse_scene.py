"""Render representative frames and a consecutive benchmark for visual QA."""
import bpy
import runpy
from pathlib import Path
import time
from mathutils import Vector

ROOT=Path(__file__).resolve().parents[2]
ns=runpy.run_path(str(ROOT/'scripts/blender/warehouse_fg_login_animation.py'))
s=bpy.context.scene
render=ns['RENDER']
for f in [180,500,1020,1600,2050,2460,2700]:
    s.frame_set(f)
    s.render.filepath=str(render/('preview_%04d.jpg'%f))
    t=time.monotonic()
    bpy.ops.render.render(write_still=True)
    print('QA_FRAME',f,'SECONDS',time.monotonic()-t,flush=True)
s.frame_start=180
s.frame_end=189
s.render.filepath=str(render/'benchmark_')
t=time.monotonic()
bpy.ops.render.render(animation=True)
print('BENCHMARK_10_SECONDS',time.monotonic()-t,flush=True)
