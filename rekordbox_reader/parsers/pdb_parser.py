"""Parser untuk Rekordbox PDB database files"""
import struct
from pathlib import Path
from typing import Dict, List, Optional, Tuple


class PDBParser:
    """Parser untuk export.pdb dan exportExt.pdb files"""
    
    # Table type constants
    TABLE_TRACKS = 0
    TABLE_GENRES = 1
    TABLE_ARTISTS = 2
    TABLE_ALBUMS = 3
    TABLE_LABELS = 4
    TABLE_KEYS = 5
    TABLE_COLORS = 6
    TABLE_PLAYLIST_TREE = 7
    TABLE_PLAYLIST_ENTRIES = 8
    TABLE_HISTORY_PLAYLISTS = 11
    TABLE_HISTORY_ENTRIES = 12
    TABLE_ARTWORK = 13
    TABLE_COLUMNS = 16
    TABLE_HISTORY = 19
    
    def __init__(self, pdb_path: Path, logger=None):
        self.pdb_path = Path(pdb_path)
        self.logger = logger
        self.data: bytes = b''
        self.header: Dict = {}
        self.tables: Dict = {}
        
    def parse(self) -> Dict:
        """Parse PDB file dan return struktur data"""
        if not self.pdb_path.exists():
            raise FileNotFoundError(f"PDB file not found: {self.pdb_path}")
        
        with open(self.pdb_path, 'rb') as f:
            self.data = f.read()
        
        if self.logger:
            self.logger.info(f"Parsing PDB file: {self.pdb_path}")
        
        # Parse header
        self.header = self._parse_header()
        
        # Parse tables
        self._parse_tables()
        
        return {
            'header': self.header,
            'tables': self.tables
        }
    
    def _parse_header(self) -> Dict:
        """Parse PDB file header"""
        if len(self.data) < 32:
            raise ValueError("PDB file too small to contain valid header")
        
        # Read header (little-endian format)
        signature = struct.unpack_from('<I', self.data, 0)[0]
        page_size = struct.unpack_from('<I', self.data, 4)[0]
        num_tables = struct.unpack_from('<I', self.data, 8)[0]
        next_unused_page = struct.unpack_from('<I', self.data, 12)[0]
        unknown = struct.unpack_from('<I', self.data, 16)[0]
        sequence = struct.unpack_from('<I', self.data, 20)[0]
        
        header = {
            'signature': signature,
            'page_size': page_size,
            'num_tables': num_tables,
            'next_unused_page': next_unused_page,
            'unknown': unknown,
            'sequence': sequence
        }
        
        if self.logger:
            self.logger.debug(f"PDB Header: {num_tables} tables, page size: {page_size} bytes")
        
        return header
    
    def _parse_tables(self):
        """Parse table pointers dari header"""
        page_size = self.header['page_size']
        num_tables = self.header['num_tables']
        
        # Table pointers start at offset 0x1C (28 bytes)
        offset = 28
        
        for i in range(num_tables):
            if offset + 16 > len(self.data):
                break
            
            table_type = struct.unpack_from('<I', self.data, offset)[0]
            empty_candidate = struct.unpack_from('<I', self.data, offset + 4)[0]
            first_page = struct.unpack_from('<I', self.data, offset + 8)[0]
            last_page = struct.unpack_from('<I', self.data, offset + 12)[0]
            
            self.tables[table_type] = {
                'type': table_type,
                'type_name': self._get_table_name(table_type),
                'empty_candidate': empty_candidate,
                'first_page': first_page,
                'last_page': last_page,
                'rows': []
            }
            
            offset += 16
    
    def _get_table_name(self, table_type: int) -> str:
        """Get human-readable table name"""
        table_names = {
            0: 'tracks',
            1: 'genres',
            2: 'artists',
            3: 'albums',
            4: 'labels',
            5: 'keys',
            6: 'colors',
            7: 'playlist_tree',
            8: 'playlist_entries',
            11: 'history_playlists',
            12: 'history_entries',
            13: 'artwork',
            16: 'columns',
            19: 'history'
        }
        return table_names.get(table_type, f'unknown_{table_type}')
    
    def get_table(self, table_type: int) -> Optional[Dict]:
        """Get table by type"""
        return self.tables.get(table_type)
    
    def read_page(self, page_index: int) -> Optional[bytes]:
        """Read a specific page from the database"""
        page_size = self.header['page_size']
        offset = page_index * page_size
        
        if offset + page_size > len(self.data):
            return None
        
        return self.data[offset:offset + page_size]
    
    def extract_string(self, data: bytes, offset: int) -> Tuple[str, int]:
        """Extract DeviceSQL string dari data"""
        if offset >= len(data):
            return "", offset
        
        flags = data[offset]
        offset += 1
        
        # Short ASCII string (S=1)
        if flags & 0x40 == 0:
            length = flags & 0x7F
            if offset + length > len(data):
                return "", offset
            text = data[offset:offset + length].decode('ascii', errors='ignore')
            return text, offset + length
        
        # Long ASCII string
        elif flags == 0x40:
            if offset + 1 >= len(data):
                return "", offset
            length = struct.unpack_from('<H', data, offset)[0]
            offset += 2
            if offset + length > len(data):
                return "", offset
            text = data[offset:offset + length].decode('ascii', errors='ignore')
            return text, offset + length
        
        # Long UTF-16LE string
        elif flags == 0x90:
            if offset + 1 >= len(data):
                return "", offset
            length = struct.unpack_from('<H', data, offset)[0]
            offset += 2
            byte_length = length * 2
            if offset + byte_length > len(data):
                return "", offset
            text = data[offset:offset + byte_length].decode('utf-16le', errors='ignore')
            return text, offset + byte_length
        
        return "", offset
