# Implementation Plan: POS Device GPS Tracker System

## Overview

This implementation plan breaks down the POS Device GPS Tracker System into three parallel development tracks: the React Native Android APK, the Laravel 11 API Backend, and the React Web Dashboard. Each track can be developed independently with integration points clearly defined. The plan follows an incremental approach where core functionality is implemented first, followed by testing, error handling, and optimization.

## Tasks

### Part 1: Laravel API Backend Setup and Core Functionality

- [x] 1. Initialize Laravel 11 project and configure environment
  - Create new Laravel 11 project with PHP 8.2+
  - Configure MySQL database connection
  - Set up environment variables for database, CORS, and API settings
  - Install Laravel Sanctum for authentication
  - Configure CORS middleware for dashboard domain
  - _Requirements: 12.1, 12.2, 12.3, 12.4_

- [x] 2. Create database schema and models
  - [x] 2.1 Create devices table migration and model
    - Migration with columns: id, device_id (unique), name, model, brand, android_version, last_seen, battery_level, timestamps
    - Add indexes on device_id and last_seen
    - Device model with fillable fields, casts, and status computed attribute
    - _Requirements: 11.1, 11.4, 7.3, 7.4_
  
  - [x] 2.2 Create location_pings table migration and model
    - Migration with columns: id, device_id, latitude, longitude, accuracy, speed, battery_level, timestamp, created_at
    - Add indexes on device_id, timestamp, and composite (device_id, timestamp)
    - Foreign key constraint on device_id with cascade delete
    - LocationPing model with fillable fields and casts
    - _Requirements: 11.2, 11.4, 11.5_
  
  - [x] 2.3 Create users table migration and seed admin user
    - Migration with columns: id, name, email (unique), password, timestamps
    - User model with HasApiTokens trait for Sanctum
    - Seeder to create initial admin user
    - _Requirements: 11.3_

- [-] 3. Implement location ping reception endpoint
  - [x] 3.1 Create LocationController with store method
    - Validate incoming location ping payload (device_id, latitude, longitude, timestamp, etc.)
    - Create or update device record based on device_id
    - Store location ping in location_pings table
    - Update device last_seen timestamp
    - Return 200 success or 422 validation error
    - No authentication required for this endpoint
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_
  
  - [ ]* 3.2 Write property test for location ping validation
    - **Property 12: Location Ping Validation**
    - **Validates: Requirements 6.1, 6.6**
  
  - [ ]* 3.3 Write property test for location ping persistence
    - **Property 13: Location Ping Persistence**
    - **Validates: Requirements 6.2**
  
  - [ ]* 3.4 Write property test for automatic device creation
    - **Property 14: Automatic Device Creation**
    - **Validates: Requirements 6.3**
  
  - [ ]* 3.5 Write property test for last seen timestamp update
    - **Property 15: Last Seen Timestamp Update**
    - **Validates: Requirements 6.4**


- [-] 4. Implement device management endpoints
  - [x] 4.1 Create DeviceController with index and show methods
    - index(): Return list of all devices with computed status, latest location
    - show($id): Return single device details or 404 if not found
    - Apply Sanctum authentication middleware
    - Include device status computation (online if last_seen < 5 minutes)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_
  
  - [ ]* 4.2 Write property test for device list response completeness
    - **Property 16: Device List Response Completeness**
    - **Validates: Requirements 7.1, 7.2**
  
  - [ ]* 4.3 Write property test for device status computation
    - **Property 17: Device Status Computation**
    - **Validates: Requirements 7.3, 7.4**
  
  - [ ]* 4.4 Write property test for device details retrieval
    - **Property 18: Device Details Retrieval**
    - **Validates: Requirements 7.5, 7.6**

- [x] 5. Implement location history endpoint
  - [x] 5.1 Add history method to DeviceController
    - Accept device_id, start_date, end_date, limit parameters
    - Query location_pings with filters and pagination
    - Order by timestamp descending
    - Default limit of 100 records
    - Return location pings with all required fields
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_
  
  - [ ]* 5.2 Write property test for location history retrieval
    - **Property 19: Location History Retrieval**
    - **Validates: Requirements 8.1**
  
  - [ ]* 5.3 Write property test for date range filtering
    - **Property 20: Date Range Filtering**
    - **Validates: Requirements 8.2, 8.3**
  
  - [ ]* 5.4 Write property test for location history response completeness
    - **Property 21: Location History Response Completeness**
    - **Validates: Requirements 8.4**
  
  - [ ]* 5.5 Write property test for location history ordering
    - **Property 22: Location History Ordering**
    - **Validates: Requirements 8.5**
  
  - [ ]* 5.6 Write property test for location history pagination
    - **Property 23: Location History Pagination**
    - **Validates: Requirements 8.6**

- [x] 6. Implement statistics endpoint
  - [x] 6.1 Create StatsController with index method
    - Calculate total device count
    - Calculate online devices count (last_seen < 5 minutes)
    - Calculate offline devices count
    - Calculate total location pings count
    - Calculate pings in last 24 hours
    - Apply Sanctum authentication middleware
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_
  
  - [ ]* 6.2 Write property test for statistics response completeness
    - **Property 24: Statistics Response Completeness**
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 9.6**

- [x] 7. Implement authentication endpoints
  - [x] 7.1 Create AuthController with login, logout, and me methods
    - login(): Validate credentials, issue Sanctum token, return token
    - logout(): Revoke current token
    - me(): Return authenticated user information
    - Handle invalid credentials with 401 error
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_
  
  - [ ]* 7.2 Write property test for authentication token issuance
    - **Property 25: Authentication Token Issuance**
    - **Validates: Requirements 10.1**
  
  - [ ]* 7.3 Write property test for invalid credentials rejection
    - **Property 26: Invalid Credentials Rejection**
    - **Validates: Requirements 10.2**
  
  - [ ]* 7.4 Write property test for token revocation
    - **Property 27: Token Revocation on Logout**
    - **Validates: Requirements 10.3**
  
  - [ ]* 7.5 Write property test for authenticated user information retrieval
    - **Property 28: Authenticated User Information Retrieval**
    - **Validates: Requirements 10.4**
  
  - [ ]* 7.6 Write property test for protected endpoint authentication enforcement
    - **Property 29: Protected Endpoint Authentication Enforcement**
    - **Validates: Requirements 10.5**

- [x] 8. Add database constraint tests and API routes
  - [x] 8.1 Define API routes in routes/api.php
    - POST /api/location (public)
    - POST /api/auth/login (public)
    - POST /api/auth/logout (protected)
    - GET /api/auth/me (protected)
    - GET /api/devices (protected)
    - GET /api/devices/{device_id} (protected)
    - GET /api/devices/{device_id}/history (protected)
    - GET /api/stats (protected)
    - _Requirements: 6.1, 7.1, 8.1, 9.1, 10.1, 10.3, 10.4_
  
  - [ ]* 8.2 Write property test for foreign key constraint enforcement
    - **Property 30: Foreign Key Constraint Enforcement**
    - **Validates: Requirements 11.5**
  
  - [ ]* 8.3 Write property test for device ID uniqueness
    - **Property 31: Device ID Uniqueness**
    - **Validates: Requirements 11.6**
  
  - [ ]* 8.4 Write property test for CORS headers presence
    - **Property 32: CORS Headers Presence**
    - **Validates: Requirements 12.1**
  
  - [ ]* 8.5 Write property test for preflight request handling
    - **Property 33: Preflight Request Handling**
    - **Validates: Requirements 12.2**

- [x] 9. Checkpoint - Ensure all API tests pass
  - Run all unit and property tests for the Laravel backend
  - Verify database migrations run successfully
  - Test API endpoints manually with Postman or similar tool
  - Ensure all tests pass, ask the user if questions arise

### Part 2: React Native Android APK Development

- [x] 10. Initialize React Native project and install dependencies
  - Create new React Native 0.73+ project
  - Install react-native-background-geolocation
  - Install react-native-device-info
  - Install @react-native-async-storage/async-storage for queue persistence
  - Install axios for HTTP requests
  - Configure Android permissions in AndroidManifest.xml
  - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [ ] 11. Implement device information collection
  - [ ] 11.1 Create DeviceInfoCollector module
    - Use react-native-device-info to collect device_id, model, brand, Android version
    - Collect battery level and charging status
    - Generate or retrieve persistent device name
    - Export functions to get device info as payload object
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  
  - [ ]* 11.2 Write unit tests for device info collection
    - Test that all required fields are collected
    - Test battery data collection
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [ ] 12. Implement background location tracking service
  - [ ] 12.1 Create BackgroundLocationService module
    - Configure react-native-background-geolocation with 60-second interval
    - Set up foreground service with persistent notification
    - Enable headless mode for post-termination tracking
    - Implement location event listener
    - Start service on app launch
    - _Requirements: 1.1, 1.2, 1.4, 1.5_
  
  - [ ]* 12.2 Write property test for location collection interval consistency
    - **Property 1: Location Collection Interval Consistency**
    - **Validates: Requirements 1.1**
  
  - [ ]* 12.3 Write property test for foreground service notification presence
    - **Property 2: Foreground Service Notification Presence**
    - **Validates: Requirements 1.2**
  
  - [ ]* 12.4 Write property test for location data completeness
    - **Property 3: Location Data Completeness**
    - **Validates: Requirements 1.5**
  
  - [ ]* 12.5 Write property test for headless task resilience
    - **Property 4: Headless Task Resilience**
    - **Validates: Requirements 1.4**

- [ ] 13. Implement location data transmission with queue
  - [ ] 13.1 Create LocationTransmitter module
    - Implement HTTP POST to /api/location endpoint
    - Combine location data with device info for payload
    - Implement offline queue using AsyncStorage
    - Implement retry logic with exponential backoff (max 3 attempts)
    - Process queue when connectivity is restored
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  
  - [ ]* 13.2 Write property test for battery data inclusion
    - **Property 5: Battery Data Inclusion**
    - **Validates: Requirements 2.3**
  
  - [ ]* 13.3 Write property test for device identification inclusion
    - **Property 6: Device Identification Inclusion**
    - **Validates: Requirements 2.4**
  
  - [ ]* 13.4 Write property test for location transmission trigger
    - **Property 7: Location Transmission Trigger**
    - **Validates: Requirements 3.1**
  
  - [ ]* 13.5 Write property test for correct endpoint usage
    - **Property 8: Correct Endpoint Usage**
    - **Validates: Requirements 3.2**
  
  - [ ]* 13.6 Write property test for offline queueing behavior
    - **Property 9: Offline Queueing Behavior**
    - **Validates: Requirements 3.3**
  
  - [ ]* 13.7 Write property test for queue processing on connectivity restoration
    - **Property 10: Queue Processing on Connectivity Restoration**
    - **Validates: Requirements 3.4**
  
  - [ ]* 13.8 Write property test for retry with exponential backoff
    - **Property 11: Retry with Exponential Backoff**
    - **Validates: Requirements 3.5**

- [ ] 14. Implement permission management
  - [ ] 14.1 Create PermissionManager module
    - Request ACCESS_FINE_LOCATION permission
    - Request ACCESS_BACKGROUND_LOCATION for Android 10+
    - Request FOREGROUND_SERVICE permission
    - Display permission rationale dialogs
    - Handle permission denial with re-request flow
    - Prompt to disable battery optimization
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  
  - [ ]* 14.2 Write unit tests for permission request flows
    - Test permission requests for different Android versions
    - Test battery optimization prompt
    - Test permission denial handling
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

- [ ] 15. Implement boot receiver for auto-start
  - [ ] 15.1 Create BootReceiver component
    - Register BOOT_COMPLETED broadcast receiver in AndroidManifest.xml
    - Implement receiver to start BackgroundLocationService on boot
    - Test boot receiver with device reboot simulation
    - _Requirements: 1.3_
  
  - [ ]* 15.2 Write unit test for boot receiver activation
    - Test that service starts after boot event
    - _Requirements: 1.3_

- [ ] 16. Configure APK build and signing
  - [ ] 16.1 Set up release build configuration
    - Generate keystore for APK signing
    - Configure build.gradle with signing config
    - Set minSdkVersion to 26 (Android 8.0)
    - Configure ProGuard rules for release builds
    - Build signed release APK
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_
  
  - [ ]* 16.2 Write unit tests for build configuration
    - Verify minSdkVersion is 26
    - Verify ProGuard rules exist
    - Verify APK is signed
    - _Requirements: 5.1, 5.2, 5.4_

- [ ] 17. Checkpoint - Test APK on physical device
  - Install APK via ADB on test device
  - Verify background tracking works
  - Verify auto-start after reboot
  - Verify location data is transmitted to API
  - Ensure all tests pass, ask the user if questions arise

### Part 3: React Web Dashboard Development

- [ ] 18. Initialize React project with TypeScript
  - Create new React 18 project with TypeScript template
  - Install dependencies: @react-google-maps/api, axios, react-router-dom
  - Install date-fns for timestamp formatting
  - Set up project structure (components, services, contexts, types)
  - Configure environment variables for API URL and Google Maps API key
  - _Requirements: 13.1, 14.1_

- [ ] 19. Implement authentication context and API service
  - [ ] 19.1 Create AuthContext for authentication state management
    - Manage token storage in localStorage
    - Provide login, logout, and token validation functions
    - Implement automatic redirect to login on 401 responses
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6_
  
  - [ ] 19.2 Create ApiService for centralized API calls
    - Configure axios with base URL and token interceptor
    - Implement typed methods for all API endpoints
    - Handle error responses and retries
    - _Requirements: 13.2, 13.5_
  
  - [ ]* 19.3 Write property test for login token storage
    - **Property 34: Login Token Storage**
    - **Validates: Requirements 13.2**
  
  - [ ]* 19.4 Write property test for logout token cleanup
    - **Property 35: Logout Token Cleanup**
    - **Validates: Requirements 13.5**
  
  - [ ]* 19.5 Write property test for invalid token redirect
    - **Property 36: Invalid Token Redirect**
    - **Validates: Requirements 13.6**

- [ ] 20. Implement login page
  - [ ] 20.1 Create LoginPage component
    - Email and password input fields
    - Submit button that calls POST /api/auth/login
    - Display error messages for failed login
    - Redirect to map interface on successful login
    - _Requirements: 13.1, 13.2, 13.3, 13.4_
  
  - [ ]* 20.2 Write unit tests for login page
    - Test form submission
    - Test error display
    - Test redirect on success
    - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [ ] 21. Implement Google Maps container and device markers
  - [ ] 21.1 Create MapContainer component
    - Initialize Google Maps with @react-google-maps/api
    - Compute map center from average device location
    - Render map with zoom and pan controls
    - _Requirements: 14.1_
  
  - [ ] 21.2 Create DeviceMarker component
    - Render marker for each device
    - Color-code markers: green for online, red for offline
    - Display info window on marker click with device details
    - Animate marker position changes
    - _Requirements: 14.2, 14.3, 14.4, 14.5, 14.6_
  
  - [ ]* 21.3 Write property test for map center computation
    - **Property 37: Map Center Computation**
    - **Validates: Requirements 14.1**
  
  - [ ]* 21.4 Write property test for marker count consistency
    - **Property 38: Marker Count Consistency**
    - **Validates: Requirements 14.2**
  
  - [ ]* 21.5 Write property test for marker color status mapping
    - **Property 39: Marker Color Status Mapping**
    - **Validates: Requirements 14.3, 14.4**
  
  - [ ]* 21.6 Write property test for marker popup content
    - **Property 40: Marker Popup Content**
    - **Validates: Requirements 14.5**
  
  - [ ]* 21.7 Write property test for marker position update
    - **Property 41: Marker Position Update**
    - **Validates: Requirements 14.6**

- [ ] 22. Implement device sidebar with list and filters
  - [ ] 22.1 Create DeviceSidebar component
    - Display list of all devices with status indicators
    - Show device name, status, and last_seen time for each item
    - Implement search filter by device name or ID
    - Implement status filter (online/offline)
    - Handle device selection to center map
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5_
  
  - [ ]* 22.2 Write property test for device list completeness
    - **Property 42: Device List Completeness**
    - **Validates: Requirements 15.1**
  
  - [ ]* 22.3 Write property test for device list item content
    - **Property 43: Device List Item Content**
    - **Validates: Requirements 15.2**
  
  - [ ]* 22.4 Write property test for search filter behavior
    - **Property 44: Search Filter Behavior**
    - **Validates: Requirements 15.3**
  
  - [ ]* 22.5 Write property test for status filter behavior
    - **Property 45: Status Filter Behavior**
    - **Validates: Requirements 15.4**
  
  - [ ]* 22.6 Write property test for device selection map centering
    - **Property 46: Device Selection Map Centering**
    - **Validates: Requirements 15.5**

- [ ] 23. Implement device details panel
  - [ ] 23.1 Create DeviceDetailsPanel component
    - Display comprehensive device information
    - Show device name, model, brand, Android version
    - Display battery level with visual indicator (progress bar or icon)
    - Format last_seen timestamp in human-readable format
    - Display GPS coordinates with Google Maps link
    - Show device status with color-coded styling
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6_
  
  - [ ]* 23.2 Write property test for device details panel display
    - **Property 47: Device Details Panel Display**
    - **Validates: Requirements 16.1, 16.2**
  
  - [ ]* 23.3 Write property test for battery level display
    - **Property 48: Battery Level Display**
    - **Validates: Requirements 16.3**
  
  - [ ]* 23.4 Write property test for timestamp formatting
    - **Property 49: Timestamp Formatting**
    - **Validates: Requirements 16.4**
  
  - [ ]* 23.5 Write property test for coordinates with Maps link
    - **Property 50: Coordinates with Maps Link**
    - **Validates: Requirements 16.5**
  
  - [ ]* 23.6 Write property test for status visual styling
    - **Property 51: Status Visual Styling**
    - **Validates: Requirements 16.6**

- [ ] 24. Implement route history visualization
  - [ ] 24.1 Create RouteHistoryLayer component
    - Fetch location history via GET /api/devices/{device_id}/history
    - Implement date range filter controls
    - Draw polyline connecting historical locations
    - Use distinct color for route polyline
    - Display start and end markers
    - Show popup on historical point click with timestamp and coordinates
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6_
  
  - [ ]* 24.2 Write property test for history API call
    - **Property 52: History API Call**
    - **Validates: Requirements 17.1**
  
  - [ ]* 24.3 Write property test for route polyline rendering
    - **Property 53: Route Polyline Rendering**
    - **Validates: Requirements 17.2**
  
  - [ ]* 24.4 Write property test for date range filter transmission
    - **Property 54: Date Range Filter Transmission**
    - **Validates: Requirements 17.4**
  
  - [ ]* 24.5 Write property test for route start and end markers
    - **Property 55: Route Start and End Markers**
    - **Validates: Requirements 17.5**
  
  - [ ]* 24.6 Write property test for historical point popup
    - **Property 56: Historical Point Popup**
    - **Validates: Requirements 17.6**

- [ ] 25. Implement statistics bar
  - [ ] 25.1 Create StatsBar component
    - Fetch statistics via GET /api/stats
    - Display total devices, online devices, offline devices, total pings
    - Auto-refresh statistics every 30 seconds
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6_
  
  - [ ]* 25.2 Write property test for statistics UI completeness
    - **Property 57: Statistics UI Completeness**
    - **Validates: Requirements 18.2, 18.3, 18.4, 18.5**
  
  - [ ]* 25.3 Write property test for statistics auto-refresh interval
    - **Property 58: Statistics Auto-Refresh Interval**
    - **Validates: Requirements 18.6**

- [ ] 26. Implement auto-refresh functionality
  - [ ] 26.1 Create useAutoRefresh custom hook
    - Implement 30-second interval for device data refresh
    - Pause refresh when tab is not visible
    - Resume refresh when tab becomes visible
    - Update map markers, device list, and statistics on refresh
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5_
  
  - [ ]* 26.2 Write property test for device data auto-refresh interval
    - **Property 59: Device Data Auto-Refresh Interval**
    - **Validates: Requirements 19.1**
  
  - [ ]* 26.3 Write property test for comprehensive UI update on refresh
    - **Property 60: Comprehensive UI Update on Refresh**
    - **Validates: Requirements 19.2**
  
  - [ ]* 26.4 Write property test for status transition UI update
    - **Property 61: Status Transition UI Update**
    - **Validates: Requirements 19.3**
  
  - [ ]* 26.5 Write property test for marker position animation
    - **Property 62: Marker Position Animation**
    - **Validates: Requirements 19.4**
  
  - [ ]* 26.6 Write property test for visibility-based refresh pause
    - **Property 63: Visibility-Based Refresh Pause**
    - **Validates: Requirements 19.5**

- [ ] 27. Implement responsive design and error handling
  - [ ] 27.1 Add responsive layout with CSS media queries
    - Desktop layout: map, sidebar, and details panel side-by-side
    - Tablet layout: adapted for medium screens
    - Mobile layout: collapsible sidebar, full-screen map
    - _Requirements: 20.1, 20.2, 20.3_
  
  - [ ] 27.2 Add loading indicators and error handling
    - Display loading spinners during data fetches
    - Show user-friendly error messages with retry buttons
    - Implement error boundaries for React component errors
    - _Requirements: 20.4, 20.5_
  
  - [ ]* 27.3 Write property test for loading indicator display
    - **Property 64: Loading Indicator Display**
    - **Validates: Requirements 20.4**
  
  - [ ]* 27.4 Write property test for error message with retry
    - **Property 65: Error Message with Retry**
    - **Validates: Requirements 20.5**

- [ ] 28. Checkpoint - Test dashboard functionality
  - Test login and authentication flow
  - Verify device markers display correctly on map
  - Test device list filtering and search
  - Verify device details panel shows all information
  - Test route history visualization with date filters
  - Verify auto-refresh updates UI every 30 seconds
  - Test responsive layout on different screen sizes
  - Ensure all tests pass, ask the user if questions arise

### Part 4: Integration and Deployment

- [ ] 29. End-to-end integration testing
  - [ ] 29.1 Test complete tracking flow
    - Install APK on test device
    - Verify location pings are received by API
    - Verify device appears on dashboard map
    - Verify real-time updates work correctly
    - _Requirements: 1.1, 3.1, 6.2, 14.2_
  
  - [ ]* 29.2 Write integration tests for authentication flow
    - Test login → access protected endpoints → logout
    - _Requirements: 10.1, 10.3, 10.5_
  
  - [ ]* 29.3 Write integration tests for offline resilience
    - Test network disconnection → queue locations → reconnect → transmit
    - _Requirements: 3.3, 3.4_

- [ ] 30. Deployment preparation
  - [ ] 30.1 Prepare Laravel API for production deployment
    - Configure production environment variables
    - Set up Nginx configuration for Laravel
    - Configure SSL certificate for HTTPS
    - Optimize database with indexes and query caching
    - Set up database backups
    - _Requirements: 12.1, 12.3_
  
  - [ ] 30.2 Prepare React dashboard for production deployment
    - Build production bundle with optimizations
    - Configure environment variables for production API URL
    - Set up hosting (Vercel, Netlify, or custom server)
    - Configure CORS on API for production dashboard domain
    - _Requirements: 12.3, 13.1_
  
  - [ ] 30.3 Prepare APK for distribution
    - Build final signed release APK
    - Create installation instructions for ADB sideloading
    - Document required permissions and battery optimization settings
    - _Requirements: 5.1, 5.3_

- [ ] 31. Final checkpoint - System verification
  - Verify all three components are deployed and running
  - Test complete flow from APK → API → Dashboard
  - Verify authentication and authorization work correctly
  - Test with multiple devices simultaneously
  - Verify auto-refresh and real-time updates
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional property-based tests and can be skipped for faster MVP
- Each task references specific requirements for traceability
- The three main parts (API, APK, Dashboard) can be developed in parallel
- Checkpoints ensure incremental validation at key milestones
- Property tests validate universal correctness properties across randomized inputs
- Unit tests validate specific examples, edge cases, and integration points
- Integration tests verify end-to-end flows across all components
