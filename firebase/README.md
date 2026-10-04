# Firebase Integration Module — UK Tutoring Platform

This directory organizes Firebase client/admin configuration documentation and templates.

## Architecture Boundary
1. **Client / Browser:** Collects credentials and authenticates with Firebase Auth.
2. **ID Token Transmission:** Client sends Firebase ID Token via `Authorization: Bearer <token>` header to the backend API.
3. **Server Verification:** PHP verifies ID Token signature and validity using the Firebase Admin SDK.
4. **Database Identity Mapping:** Backend extracts Firebase UID and maps to MySQL `users.firebase_uid`.
5. **Server-Side Authorization Authority:** MySQL role assignment is authoritative. Browser claims are never trusted for role authorization.

*Note: Private Firebase service-account JSON keys reside strictly in `storage/credentials/` and must never be exposed.* 
