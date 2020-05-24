# VMWareAPI version 0.1
Copyright (c) 2020 P Matthew Bradford

## Dependencies:
DOMDocumentExtended.class.php - By Mathieu Bouchard

## Description
An API for VMWare designed to gather performance telemetry from a VMWare installation that is easy to use.

## Notable features:
- Flexible return types - Can return raw XML, JSON, or PHP arrays.
- Rate limiting - Ensure you don't overwhelm your server with too many requests
- Performance statistics - Keep track of how well the API is performing for tuning
- Low-level exposure to API - Submit any valid SOAP call directly
- Mid-level abstractions - All inventory and telemetry related functions are wrapped in easy to use functions
- High-level abstractions - Combine several calls to get commonly desired results (ex: show me all the metrics in plain english available for all VMs
  
And much more!
  
