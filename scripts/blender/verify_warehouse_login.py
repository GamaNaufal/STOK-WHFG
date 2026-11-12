"""Verify encoded Warehouse FG media with FFprobe and decoded FFmpeg frames.

Run after encode_warehouse_login.py. Requires only the Python standard library.
This checks technical delivery; the composed shots still require visual review.
"""

from __future__ import annotations

import argparse
from fractions import Fraction
import json
from pathlib import Path
import shutil
import struct
import subprocess


ROOT = Path(__file__).resolve().parents[2]


def probe(executable: str, path: Path) -> dict:
    result = subprocess.run(
        [executable, "-v", "error", "-count_frames", "-show_streams", "-show_format",
         "-of", "json", str(path)], capture_output=True, text=True, check=True,
    )
    return json.loads(result.stdout)


def frame(executable: str, path: Path, time: float) -> bytes:
    return subprocess.run(
        [executable, "-v", "error", "-nostdin", "-ss", str(time), "-i", str(path),
         "-frames:v", "1", "-vf", "scale=160:90", "-pix_fmt", "rgb24",
         "-f", "rawvideo", "pipe:1"], capture_output=True, check=True,
    ).stdout


def top_level_boxes(path: Path) -> list[str]:
    """Read MP4 top-level atom headers without loading the media into RAM."""
    names = []
    with path.open("rb") as stream:
        end = path.stat().st_size
        while stream.tell() + 8 <= end:
            start = stream.tell()
            size, name = struct.unpack(">I4s", stream.read(8))
            header_size = 8
            if size == 1:
                size = struct.unpack(">Q", stream.read(8))[0]
                header_size = 16
            elif size == 0:
                size = end - start
            if size < header_size or start + size > end:
                raise ValueError(f"Invalid MP4 atom at byte {start}: {path}")
            names.append(name.decode("ascii", errors="replace"))
            stream.seek(start + size)
    return names


def inspect_video(ffprobe: str, ffmpeg: str, path: Path, width: int, height: int,
                  duration: int = 90, black_edges: bool = True) -> dict:
    metadata = probe(ffprobe, path)
    streams = metadata["streams"]
    videos = [stream for stream in streams if stream["codec_type"] == "video"]
    assert len(videos) == 1, f"Expected one video stream: {path}"
    assert not any(stream["codec_type"] == "audio" for stream in streams), f"Unexpected audio: {path}"
    video = videos[0]
    assert video["codec_name"] == "h264", f"Expected H.264: {path}"
    assert (video["width"], video["height"]) == (width, height), f"Wrong dimensions: {path}"
    assert Fraction(video["avg_frame_rate"]) == 30, f"Wrong FPS: {path}"
    count = int(video.get("nb_read_frames") or video["nb_frames"])
    assert count == duration * 30, f"Expected {duration * 30} decoded frames, got {count}: {path}"
    seconds = float(metadata["format"]["duration"])
    assert abs(seconds - duration) < 0.001, f"Wrong duration {seconds}: {path}"
    boxes = top_level_boxes(path)
    assert "moov" in boxes and "mdat" in boxes and boxes.index("moov") < boxes.index("mdat"), f"MP4 is not faststart: {path}"
    result = {"path": str(path), "bytes": path.stat().st_size, "codec": video["codec_name"],
              "width": width, "height": height, "fps": 30, "decoded_frames": count,
              "duration_seconds": seconds, "audio_streams": 0, "faststart": True}
    if black_edges:
        first = frame(ffmpeg, path, 0)
        last = frame(ffmpeg, path, (count - 1) / 30)
        assert len(first) == 160 * 90 * 3 and len(last) == len(first), f"Could not decode edge frames: {path}"
        edge_max = [max(first), max(last)]
        assert max(edge_max) <= 4, f"Loop edges must be black, RGB maxima {edge_max}: {path}"
        result["edge_frame_max_rgb"] = edge_max
    return result


def inspect_motion(ffmpeg: str, path: Path) -> list[dict]:
    results = []
    for name, start, end in [("introduction", 4, 8), ("inbound", 13, 20),
                             ("storage", 28, 40), ("picking", 48, 56),
                             ("outbound", 63, 71)]:
        first, last = frame(ffmpeg, path, start), frame(ffmpeg, path, end)
        assert len(first) == 160 * 90 * 3 and len(last) == len(first), f"Missing {name} frames"
        difference = sum(abs(a - b) for a, b in zip(first, last)) / len(first)
        assert difference > 0.1, f"No visible movement across {name} samples"
        results.append({"scene": name, "sample_times_seconds": [start, end],
                        "mean_rgb_difference": round(difference, 4)})
    return results


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ffmpeg", default=shutil.which("ffmpeg"))
    parser.add_argument("--ffprobe", default=shutil.which("ffprobe"))
    parser.add_argument("--master-dir", type=Path, default=ROOT / "artifacts/warehouse-login")
    parser.add_argument("--web-dir", type=Path, default=ROOT / "public/media/warehouse-login")
    args = parser.parse_args()
    if not args.ffmpeg or not args.ffprobe:
        parser.error("Provide --ffmpeg and --ffprobe or put both executables on PATH")
    master = args.master_dir / "warehouse_fg_login_master_90s.mp4"
    desktop = args.web_dir / "warehouse_fg_login_desktop.mp4"
    report = {"videos": [
        inspect_video(args.ffprobe, args.ffmpeg, master, 1920, 1080),
        inspect_video(args.ffprobe, args.ffmpeg, desktop, 1280, 720),
        inspect_video(args.ffprobe, args.ffmpeg,
                      args.master_dir / "warehouse_fg_login_loop_test.mp4", 1280, 720,
                      duration=10, black_edges=False),
    ], "motion_samples": inspect_motion(args.ffmpeg, master), "posters": []}
    for name, expected_width in [("poster", 1280), ("mobile", 960)]:
        path = args.web_dir / f"warehouse_fg_login_{name}.webp"
        stream = probe(args.ffprobe, path)["streams"][0]
        assert stream["codec_name"] == "webp" and stream["width"] == expected_width, f"Wrong poster: {path}"
        assert stream["height"] > 0 and path.stat().st_size > 0, f"Empty poster: {path}"
        report["posters"].append({"path": str(path), "width": stream["width"],
                                 "height": stream["height"], "bytes": path.stat().st_size})
    # The tail/head seam is the end of the previous loop and start of the next.
    loop = args.master_dir / "warehouse_fg_login_loop_test.mp4"
    seam = [frame(args.ffmpeg, loop, 149 / 30), frame(args.ffmpeg, loop, 150 / 30)]
    assert all(len(sample) == 160 * 90 * 3 for sample in seam), "Could not decode loop seam"
    assert all(max(sample) <= 4 for sample in seam), "Loop preview seam must remain black"
    report["loop_seam_max_rgb"] = [max(sample) for sample in seam]
    report["status"] = "passed"
    destination = args.master_dir / "verification-report.json"
    destination.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
