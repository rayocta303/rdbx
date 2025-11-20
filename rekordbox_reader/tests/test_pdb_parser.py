"""Unit tests untuk PDB parser"""
import pytest
import struct
from pathlib import Path
from unittest.mock import Mock, patch, mock_open
from rekordbox_reader.parsers.pdb_parser import PDBParser


class TestPDBParser:
    """Test cases untuk PDBParser"""
    
    def test_get_table_name(self):
        """Test table name mapping"""
        parser = PDBParser(Path("fake.pdb"))
        
        assert parser._get_table_name(0) == 'tracks'
        assert parser._get_table_name(1) == 'genres'
        assert parser._get_table_name(7) == 'playlist_tree'
        assert parser._get_table_name(8) == 'playlist_entries'
        assert parser._get_table_name(999) == 'unknown_999'
    
    def test_extract_string_short_ascii(self):
        """Test extraction of short ASCII string"""
        parser = PDBParser(Path("fake.pdb"))
        
        # Short ASCII: flags=0x05 (length 5), then "Hello"
        data = b'\x05Hello\x00\x00'
        
        text, new_offset = parser.extract_string(data, 0)
        
        assert text == 'Hello'
        assert new_offset == 6  # 1 byte flag + 5 bytes text
    
    def test_extract_string_empty_data(self):
        """Test extraction with empty data"""
        parser = PDBParser(Path("fake.pdb"))
        
        data = b''
        
        text, new_offset = parser.extract_string(data, 0)
        
        assert text == ""
        assert new_offset == 0
    
    def test_extract_string_offset_beyond_data(self):
        """Test extraction with offset beyond data"""
        parser = PDBParser(Path("fake.pdb"))
        
        data = b'Hello'
        
        text, new_offset = parser.extract_string(data, 100)
        
        assert text == ""
        assert new_offset == 100


class TestPDBParserIntegration:
    """Integration tests untuk PDB parser"""
    
    def create_mock_pdb_data(self):
        """Create minimal valid PDB data"""
        data = bytearray(1024)
        
        # Header (little-endian)
        struct.pack_into('<I', data, 0, 0)  # signature
        struct.pack_into('<I', data, 4, 512)  # page_size
        struct.pack_into('<I', data, 8, 1)  # num_tables
        struct.pack_into('<I', data, 12, 2)  # next_unused_page
        struct.pack_into('<I', data, 16, 0)  # unknown
        struct.pack_into('<I', data, 20, 1)  # sequence
        
        # Table pointer
        struct.pack_into('<I', data, 28, 0)  # table_type (tracks)
        struct.pack_into('<I', data, 32, 0)  # empty_candidate
        struct.pack_into('<I', data, 36, 1)  # first_page
        struct.pack_into('<I', data, 40, 1)  # last_page
        
        return bytes(data)
    
    @patch('builtins.open', new_callable=mock_open)
    @patch('pathlib.Path.exists')
    def test_parse_valid_pdb(self, mock_exists, mock_file):
        """Test parsing valid PDB data"""
        mock_exists.return_value = True
        mock_data = self.create_mock_pdb_data()
        mock_file.return_value.read.return_value = mock_data
        
        parser = PDBParser(Path("test.pdb"))
        result = parser.parse()
        
        assert 'header' in result
        assert 'tables' in result
        assert result['header']['num_tables'] == 1
        assert result['header']['page_size'] == 512
