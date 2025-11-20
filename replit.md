# Rekordbox USB Viewer

## Overview
A web application that displays and plays music from a Rekordbox USB export. This project was imported from a GitHub repository containing Rekordbox DJ data files (music, metadata, and Pioneer database files).

## Project Structure
- `server.js` - Express server that serves the web interface and provides API endpoints
- `public/` - Frontend files (HTML, CSS, JavaScript)
- `Rekordbox-USB/` - Original Rekordbox data export containing:
  - `Contents/` - MP3 music files organized by artist/album
  - `PIONEER/` - Rekordbox metadata, artwork, and analysis files

## Features
- Browse music library by tracks and artists
- Search functionality for tracks, artists, and albums
- Web-based audio player
- Displays track metadata and artist information

## Technology Stack
- Backend: Node.js with Express
- Frontend: Vanilla HTML, CSS, JavaScript
- Server runs on port 5000

## Setup and Running
The project is configured to run automatically with:
- Workflow: "Rekordbox Viewer" runs `npm start`
- Server binds to 0.0.0.0:5000 for Replit preview
- Deployment configured for autoscale

## Recent Changes (Nov 20, 2025)
- Converted Rekordbox data repository into a functional web application
- Created Express server with API endpoint for library browsing
- Built responsive web interface with search and playback features
- Configured for Replit environment with proper port binding and deployment settings
