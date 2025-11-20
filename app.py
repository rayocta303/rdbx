#!/usr/bin/env python3
"""
Rekordbox Export Reader - Web Interface
Flask web application untuk parsing Rekordbox exports
"""
import os
import json
from pathlib import Path
from flask import Flask, render_template, request, jsonify, send_file
from datetime import datetime

from rekordbox_reader.parsers.pdb_parser import PDBParser
from rekordbox_reader.parsers.track_parser import TrackParser
from rekordbox_reader.parsers.playlist_parser import PlaylistParser
from rekordbox_reader.parsers.anlz_parser import ANLZParser, find_anlz_files
from rekordbox_reader.utils.logger import RekordboxLogger

app = Flask(__name__, 
            template_folder='web/templates',
            static_folder='web/static')

# Global storage untuk hasil parsing
parsing_results = {}
parsing_stats = {}

@app.route('/')
def index():
    """Homepage"""
    return render_template('index.html')

@app.route('/api/parse', methods=['POST'])
def parse_export():
    """Parse Rekordbox export"""
    try:
        data = request.get_json()
        export_path = data.get('export_path', '')
        
        if not export_path:
            # Check if there's a default Rekordbox-USB path
            default_path = Path('Rekordbox-USB')
            if default_path.exists():
                export_path = str(default_path)
            else:
                return jsonify({'error': 'No export path provided'}), 400
        
        export_path = Path(export_path)
        
        if not export_path.exists():
            return jsonify({'error': f'Path not found: {export_path}'}), 404
        
        # Find PDB file
        pdb_path = export_path / "PIONEER" / "rekordbox" / "export.pdb"
        
        if not pdb_path.exists():
            return jsonify({'error': f'export.pdb not found at {pdb_path}'}), 404
        
        # Initialize logger
        logger = RekordboxLogger("output", verbose=False)
        
        # Parse PDB
        pdb_parser = PDBParser(pdb_path, logger)
        pdb_data = pdb_parser.parse()
        
        # Parse tracks
        track_parser = TrackParser(pdb_parser, logger)
        tracks = track_parser.parse_tracks()
        
        # Parse playlists
        playlist_parser = PlaylistParser(pdb_parser, logger)
        playlists = playlist_parser.parse_playlists()
        playlist_stats = playlist_parser.get_stats()
        
        # Find ANLZ files
        pioneer_path = export_path / "PIONEER"
        anlz_files = []
        if pioneer_path.exists():
            anlz_files = find_anlz_files(pioneer_path)
        
        # Store results
        result = {
            'tracks': tracks,
            'playlists': playlists,
            'metadata': {
                'export_path': str(export_path),
                'parsed_at': datetime.now().isoformat(),
                'pdb_header': pdb_data.get('header', {})
            }
        }
        
        stats = {
            'total_tracks': len(tracks),
            'total_playlists': playlist_stats['total_playlists'],
            'valid_playlists': playlist_stats['valid_playlists'],
            'corrupt_playlists': playlist_stats['corrupt_playlists'],
            'anlz_files_found': len(anlz_files)
        }
        
        # Save to global storage
        parsing_results['latest'] = result
        parsing_stats['latest'] = stats
        
        # Get corrupt playlists log
        logger.save_corrupt_playlist_log()
        corrupt_playlists = logger.corrupt_playlists
        
        return jsonify({
            'success': True,
            'stats': stats,
            'corrupt_playlists': corrupt_playlists
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/tracks')
def get_tracks():
    """Get parsed tracks"""
    result = parsing_results.get('latest', {})
    tracks = result.get('tracks', [])
    return jsonify({'tracks': tracks})

@app.route('/api/playlists')
def get_playlists():
    """Get parsed playlists"""
    result = parsing_results.get('latest', {})
    playlists = result.get('playlists', [])
    return jsonify({'playlists': playlists})

@app.route('/api/stats')
def get_stats():
    """Get parsing statistics"""
    stats = parsing_stats.get('latest', {})
    return jsonify(stats)

@app.route('/api/export/json')
def export_json():
    """Export results as JSON file"""
    result = parsing_results.get('latest', {})
    
    if not result:
        return jsonify({'error': 'No data to export'}), 404
    
    # Save to temporary file
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    output_file = Path("output") / f"rekordbox_export_{timestamp}.json"
    output_file.parent.mkdir(exist_ok=True)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(result, f, indent=2, ensure_ascii=False)
    
    return send_file(output_file, as_attachment=True, download_name=f"rekordbox_export_{timestamp}.json")

@app.route('/api/demo/parse')
def demo_parse():
    """Parse demo/mock export"""
    # Create or use existing mock export
    mock_path = Path("rekordbox_reader/examples/mock_export")
    
    if not mock_path.exists():
        from rekordbox_reader.examples.create_mock_export import create_mock_export
        create_mock_export(mock_path)
    
    # Parse the mock export
    try:
        logger = RekordboxLogger("output", verbose=False)
        pdb_path = mock_path / "PIONEER" / "rekordbox" / "export.pdb"
        
        pdb_parser = PDBParser(pdb_path, logger)
        pdb_data = pdb_parser.parse()
        
        track_parser = TrackParser(pdb_parser, logger)
        tracks = track_parser.parse_tracks()
        
        playlist_parser = PlaylistParser(pdb_parser, logger)
        playlists = playlist_parser.parse_playlists()
        playlist_stats = playlist_parser.get_stats()
        
        result = {
            'tracks': tracks,
            'playlists': playlists,
            'metadata': {
                'export_path': str(mock_path),
                'parsed_at': datetime.now().isoformat(),
                'pdb_header': pdb_data.get('header', {})
            }
        }
        
        stats = {
            'total_tracks': len(tracks),
            'total_playlists': playlist_stats['total_playlists'],
            'valid_playlists': playlist_stats['valid_playlists'],
            'corrupt_playlists': playlist_stats['corrupt_playlists'],
            'anlz_files_found': 0
        }
        
        parsing_results['latest'] = result
        parsing_stats['latest'] = stats
        
        return jsonify({
            'success': True,
            'stats': stats,
            'message': 'Demo export parsed successfully'
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
