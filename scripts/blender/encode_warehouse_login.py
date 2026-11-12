"""Encode the actual 2,700 Blender frames; never interpolate or duplicate frames.

Requires FFmpeg on PATH or --ffmpeg. All paths are relative to this repository
unless explicitly supplied. The Blender script documents how to render frames.
"""

from __future__ import annotations

import argparse
import json
import shutil
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
FPS = 30
FRAME_COUNT = 2700


def run(executable: str, *arguments: str) -> None:
    subprocess.run(
        [executable, "-hide_banner", "-nostdin", "-y", *map(str, arguments)],
        check=True,
    )


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("frames", type=Path, help="Directory containing frame_0001.jpg, etc.")
    parser.add_argument("--pattern", default="frame_%04d.jpg")
    parser.add_argument("--ffmpeg", default=shutil.which("ffmpeg"))
    parser.add_argument("--master-dir", type=Path, default=ROOT / "artifacts/warehouse-login")
    parser.add_argument("--web-dir", type=Path, default=ROOT / "public/media/warehouse-login")
    parser.add_argument("--poster-time", type=float, default=8.0)
    parser.add_argument("--webm", action="store_true", help="Also encode optional VP9 WebM")
    args = parser.parse_args()
    if not args.ffmpeg:
        parser.error("Provide --ffmpeg or put FFmpeg on PATH")
    for frame in range(1, FRAME_COUNT + 1):
        source = args.frames / (args.pattern % frame)
        if not source.is_file() or source.stat().st_size == 0:
            parser.error(f"Missing or empty source frame: {source}")
    args.master_dir.mkdir(parents=True, exist_ok=True)
    args.web_dir.mkdir(parents=True, exist_ok=True)
    master = args.master_dir / "warehouse_fg_login_master_90s.mp4"
    desktop = args.web_dir / "warehouse_fg_login_desktop.mp4"
    loop = args.master_dir / "warehouse_fg_login_loop_test.mp4"
    # Native 1920 x 1080 frames, exact timestamps and frame count, no audio.
    run(args.ffmpeg, "-framerate", FPS, "-start_number", "1", "-i",
        args.frames / args.pattern, "-frames:v", FRAME_COUNT,
        "-c:v", "libx264", "-preset", "medium", "-crf", "18",
        "-pix_fmt", "yuv420p", "-an", "-movflags", "+faststart", master)
    run(args.ffmpeg, "-i", master, "-vf", "scale=1280:720:flags=lanczos",
        "-c:v", "libx264", "-preset", "medium", "-crf", "25",
        "-maxrate", "1800k", "-bufsize", "3600k", "-pix_fmt", "yuv420p",
        "-an", "-movflags", "+faststart", desktop)
    for name, width in [("poster", 1280), ("mobile", 960)]:
        run(args.ffmpeg, "-ss", args.poster_time, "-i", master, "-frames:v", "1",
            "-vf", f"scale={width}:-2:flags=lanczos", "-c:v", "libwebp",
            "-quality", "86", args.web_dir / f"warehouse_fg_login_{name}.webp")
    # Last five seconds immediately followed by first five seconds, not a cut
    # between unrelated scenes. The seam sits exactly at the midpoint.
    run(args.ffmpeg, "-i", desktop, "-filter_complex",
        "[0:v]split=2[a][b];[a]trim=start=85:end=90,setpts=PTS-STARTPTS[tail];"
        "[b]trim=start=0:end=5,setpts=PTS-STARTPTS[head];"
        "[tail][head]concat=n=2:v=1:a=0[out]",
        "-map", "[out]", "-c:v", "libx264", "-crf", "20",
        "-pix_fmt", "yuv420p", "-an", "-movflags", "+faststart", loop)
    if args.webm:
        run(args.ffmpeg, "-i", desktop, "-c:v", "libvpx-vp9", "-crf", "34",
            "-b:v", "0", "-row-mt", "1", "-an",
            args.web_dir / "warehouse_fg_login_desktop.webm")
    outputs = [master, desktop, loop, *args.web_dir.glob("*.webp")]
    def display_path(path: Path) -> str:
        try:
            return str(path.relative_to(ROOT))
        except ValueError:
            return str(path)

    report = {
        "source_frame_count": FRAME_COUNT,
        "source_fps": FPS,
        "duration_seconds": FRAME_COUNT / FPS,
        "files": {display_path(path): path.stat().st_size for path in outputs},
    }
    (args.master_dir / "encoding-report.json").write_text(
        json.dumps(report, indent=2) + "\n", encoding="utf-8"
    )
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
