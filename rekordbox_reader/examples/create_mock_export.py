"""Create mock Rekordbox export data untuk testing"""
import struct
from pathlib import Path


def create_mock_export(export_path: Path):
    """Create minimal mock Rekordbox export structure"""
    
    # Create directory structure
    pioneer_dir = export_path / "PIONEER"
    rekordbox_dir = pioneer_dir / "rekordbox"
    anlz_dir = pioneer_dir / "USBANLZ" / "P000" / "00000001"
    
    rekordbox_dir.mkdir(parents=True, exist_ok=True)
    anlz_dir.mkdir(parents=True, exist_ok=True)
    
    # Create minimal export.pdb
    create_mock_pdb(rekordbox_dir / "export.pdb")
    
    # Create minimal ANLZ file
    create_mock_anlz(anlz_dir / "ANLZ0000.DAT")
    
    print(f"Mock export created at: {export_path}")
    print(f"  - {rekordbox_dir / 'export.pdb'}")
    print(f"  - {anlz_dir / 'ANLZ0000.DAT'}")


def create_mock_pdb(pdb_path: Path):
    """Create minimal valid PDB file"""
    data = bytearray(2048)
    
    # Header (little-endian)
    struct.pack_into('<I', data, 0, 0)  # signature
    struct.pack_into('<I', data, 4, 512)  # page_size
    struct.pack_into('<I', data, 8, 3)  # num_tables (tracks, artists, playlists)
    struct.pack_into('<I', data, 12, 4)  # next_unused_page
    struct.pack_into('<I', data, 16, 0)  # unknown
    struct.pack_into('<I', data, 20, 1)  # sequence
    
    # Table pointers (16 bytes each)
    offset = 28
    
    # Table 0: Tracks
    struct.pack_into('<I', data, offset, 0)  # table_type
    struct.pack_into('<I', data, offset + 4, 0)  # empty_candidate
    struct.pack_into('<I', data, offset + 8, 1)  # first_page
    struct.pack_into('<I', data, offset + 12, 1)  # last_page
    offset += 16
    
    # Table 2: Artists
    struct.pack_into('<I', data, offset, 2)  # table_type
    struct.pack_into('<I', data, offset + 4, 0)  # empty_candidate
    struct.pack_into('<I', data, offset + 8, 2)  # first_page
    struct.pack_into('<I', data, offset + 12, 2)  # last_page
    offset += 16
    
    # Table 7: Playlist Tree
    struct.pack_into('<I', data, offset, 7)  # table_type
    struct.pack_into('<I', data, offset + 4, 0)  # empty_candidate
    struct.pack_into('<I', data, offset + 8, 3)  # first_page
    struct.pack_into('<I', data, offset + 12, 3)  # last_page
    
    # Write to file
    with open(pdb_path, 'wb') as f:
        f.write(data)


def create_mock_anlz(anlz_path: Path):
    """Create minimal valid ANLZ file"""
    data = bytearray(1024)
    
    # PMAI header (big-endian)
    data[0:4] = b'PMAI'
    struct.pack_into('>I', data, 4, 28)  # len_header
    struct.pack_into('>I', data, 8, len(data))  # len_file
    
    # Add a simple PPTH section
    offset = 28
    data[offset:offset+4] = b'PPTH'
    struct.pack_into('>I', data, offset + 4, 0)  # header_len
    struct.pack_into('>I', data, offset + 8, 64)  # section_len
    
    # Write to file
    with open(anlz_path, 'wb') as f:
        f.write(data)


if __name__ == '__main__':
    # Create mock export in examples directory
    export_path = Path(__file__).parent / "mock_export"
    create_mock_export(export_path)
