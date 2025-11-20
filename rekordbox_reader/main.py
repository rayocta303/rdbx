#!/usr/bin/env python3
"""
Rekordbox Export Reader - Main CLI Interface
Reads and processes Pioneer Rekordbox USB/SD exports
"""
import argparse
import json
import time
from pathlib import Path
from datetime import datetime

from rekordbox_reader.parsers.pdb_parser import PDBParser
from rekordbox_reader.parsers.track_parser import TrackParser
from rekordbox_reader.parsers.playlist_parser import PlaylistParser
from rekordbox_reader.parsers.anlz_parser import ANLZParser, find_anlz_files
from rekordbox_reader.utils.logger import RekordboxLogger


class RekordboxExportReader:
    """Main class untuk reading Rekordbox exports"""
    
    def __init__(self, export_path: str, output_dir: str = "output", verbose: bool = False):
        self.export_path = Path(export_path)
        self.output_dir = Path(output_dir)
        self.verbose = verbose
        
        # Create output directory
        self.output_dir.mkdir(exist_ok=True)
        
        # Initialize logger
        self.logger = RekordboxLogger(str(self.output_dir), verbose)
        
        # Find database files
        self.pdb_path = self.export_path / "PIONEER" / "rekordbox" / "export.pdb"
        self.pdb_ext_path = self.export_path / "PIONEER" / "rekordbox" / "exportExt.pdb"
        
        # Statistics
        self.stats = {
            'total_tracks': 0,
            'total_playlists': 0,
            'valid_playlists': 0,
            'corrupt_playlists': 0,
            'anlz_files_processed': 0,
            'processing_time': 0
        }
    
    def run(self) -> dict:
        """Run the complete extraction process"""
        self.logger.info("=" * 60)
        self.logger.info("Rekordbox Export Reader - Starting...")
        self.logger.info("=" * 60)
        
        start_time = time.time()
        
        try:
            # Parse PDB database
            result = self._parse_database()
            
            # Parse ANLZ files
            self._parse_anlz_files()
            
            # Save output
            self._save_output(result)
            
            # Calculate processing time
            self.stats['processing_time'] = int(time.time() - start_time)
            
            # Print summary
            self._print_summary()
            
            # Save corrupt playlist log
            self.logger.save_corrupt_playlist_log()
            
            return result
            
        except Exception as e:
            self.logger.error(f"Fatal error: {e}")
            raise
    
    def _parse_database(self) -> dict:
        """Parse PDB database files"""
        result = {
            'tracks': [],
            'playlists': [],
            'metadata': {}
        }
        
        # Check if export.pdb exists
        if not self.pdb_path.exists():
            self.logger.error(f"Database not found: {self.pdb_path}")
            raise FileNotFoundError(f"export.pdb not found at {self.pdb_path}")
        
        # Parse main PDB file
        self.logger.info(f"Reading database: {self.pdb_path}")
        pdb_parser = PDBParser(self.pdb_path, self.logger)
        pdb_data = pdb_parser.parse()
        
        # Parse tracks
        track_parser = TrackParser(pdb_parser, self.logger)
        tracks = track_parser.parse_tracks()
        result['tracks'] = tracks
        self.stats['total_tracks'] = len(tracks)
        
        # Parse playlists dengan corruption handling
        playlist_parser = PlaylistParser(pdb_parser, self.logger)
        playlists = playlist_parser.parse_playlists()
        result['playlists'] = playlists
        
        # Update stats
        playlist_stats = playlist_parser.get_stats()
        self.stats['total_playlists'] = playlist_stats['total_playlists']
        self.stats['valid_playlists'] = playlist_stats['valid_playlists']
        self.stats['corrupt_playlists'] = playlist_stats['corrupt_playlists']
        
        # Add metadata
        result['metadata'] = {
            'export_path': str(self.export_path),
            'database_file': str(self.pdb_path),
            'parsed_at': datetime.now().isoformat(),
            'pdb_header': pdb_data.get('header', {})
        }
        
        return result
    
    def _parse_anlz_files(self):
        """Parse ANLZ analysis files"""
        pioneer_path = self.export_path / "PIONEER"
        
        if not pioneer_path.exists():
            self.logger.warning("PIONEER directory not found, skipping ANLZ parsing")
            return
        
        # Find all ANLZ files
        anlz_files = find_anlz_files(pioneer_path)
        
        if not anlz_files:
            self.logger.info("No ANLZ files found")
            return
        
        self.logger.info(f"Found {len(anlz_files)} ANLZ files")
        
        # Parse first few ANLZ files as examples
        for i, anlz_file in enumerate(anlz_files[:5]):  # Limit to first 5
            try:
                parser = ANLZParser(anlz_file, self.logger)
                anlz_data = parser.parse()
                
                if anlz_data:
                    self.stats['anlz_files_processed'] += 1
                    
            except Exception as e:
                self.logger.debug(f"Error parsing {anlz_file.name}: {e}")
    
    def _save_output(self, result: dict):
        """Save result to JSON file"""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = self.output_dir / f"rekordbox_export_{timestamp}.json"
        
        # Save complete result
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(result, f, indent=2, ensure_ascii=False)
        
        self.logger.info(f"Output saved to: {output_file}")
        
        # Save stats separately
        stats_file = self.output_dir / f"stats_{timestamp}.json"
        with open(stats_file, 'w', encoding='utf-8') as f:
            json.dump(self.stats, f, indent=2)
    
    def _print_summary(self):
        """Print processing summary"""
        self.logger.info("")
        self.logger.info("=" * 60)
        self.logger.info("PROCESSING SUMMARY")
        self.logger.info("=" * 60)
        self.logger.info(f"Total Tracks:           {self.stats['total_tracks']}")
        self.logger.info(f"Total Playlists:        {self.stats['total_playlists']}")
        self.logger.info(f"  - Valid:              {self.stats['valid_playlists']}")
        self.logger.info(f"  - Corrupt (skipped):  {self.stats['corrupt_playlists']}")
        self.logger.info(f"ANLZ Files Processed:   {self.stats['anlz_files_processed']}")
        self.logger.info(f"Processing Time:        {self.stats['processing_time']:.2f} seconds")
        self.logger.info("=" * 60)


def main():
    """Main CLI entry point"""
    parser = argparse.ArgumentParser(
        description='Rekordbox Export Reader - Parse and process Rekordbox USB/SD exports',
        formatter_class=argparse.RawDescriptionHelpFormatter
    )
    
    parser.add_argument(
        'export_path',
        help='Path to Rekordbox USB/SD export (containing PIONEER directory)'
    )
    
    parser.add_argument(
        '-o', '--output',
        default='output',
        help='Output directory for JSON files and logs (default: output)'
    )
    
    parser.add_argument(
        '-v', '--verbose',
        action='store_true',
        help='Enable verbose debug logging'
    )
    
    args = parser.parse_args()
    
    # Run the reader
    reader = RekordboxExportReader(
        export_path=args.export_path,
        output_dir=args.output,
        verbose=args.verbose
    )
    
    try:
        reader.run()
        return 0
    except Exception as e:
        print(f"Error: {e}")
        return 1


if __name__ == '__main__':
    exit(main())
