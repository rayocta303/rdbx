#!/usr/bin/env python3
"""Demo script for Rekordbox Export Reader"""
import sys
from pathlib import Path

# Create mock export and run tests
print("=" * 60)
print("Rekordbox Export Reader - Demo")
print("=" * 60)
print()
print("This project provides a comprehensive parser for")
print("Pioneer Rekordbox USB/SD exports.")
print()
print("Features:")
print("  ✓ Parse export.pdb and exportExt.pdb databases")
print("  ✓ Extract tracks, playlists, and metadata")
print("  ✓ Detect and skip corrupt playlists safely")
print("  ✓ Parse ANLZ files (beatgrid, waveform, cue points)")
print("  ✓ Export to JSON format")
print()
print("=" * 60)
print("Creating mock export data...")
print("=" * 60)

# Create mock data
from rekordbox_reader.examples.create_mock_export import create_mock_export

mock_path = Path("rekordbox_reader/examples/mock_export")
create_mock_export(mock_path)

print()
print("=" * 60)
print("Running unit tests...")
print("=" * 60)
print()

# Run tests
import subprocess
result = subprocess.run(['pytest', 'rekordbox_reader/tests/', '-v'], capture_output=False)

print()
print("=" * 60)
print("Demo completed!")
print("=" * 60)
print()
print("To use with your own Rekordbox export:")
print(f"  python run.py /path/to/your/usb/export")
print()
print("For more information, see README.md")
print()

sys.exit(result.returncode)
