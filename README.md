# Job Portal Sprint 1 MVP

This workspace now contains a self-contained PHP + MySQL starter for the Sprint 1 job portal scope:

- User authentication with password hashing
- Profile management
- Employer job posting CRUD
- Job search and filters
- Saved jobs
- Resume upload and management
- Job applications with status tracking
- Dashboards for job seekers and employers
- In-app notifications
- Password reset flow using emailed 6-digit verification codes

## Run locally

1. Open the project in XAMPP or run `php -S localhost:8000` from this folder.
2. Visit `http://localhost/Portal/` or the PHP server URL.
3. Create or verify your XAMPP MySQL connection settings in `includes/db-config.php`.
4. To send password reset emails to real inboxes, update `includes/mail-config.php` with your SMTP credentials and set `'enabled' => true`.
5. Demo accounts:
   - Employer: `employer@example.com` / `password123`
   - Job seeker: `seeker@example.com` / `password123`

## Notes

- Data is stored in MySQL. In this workspace, the app is configured to use database `job_portal` on `127.0.0.1:3307`.
- Uploaded resumes are stored in `storage/resumes/`.
- If SMTP is not configured, failed emails are written to `storage/mail/` for local development.
- Password reset emails include a 6-digit code instead of a clickable link. On local development without SMTP, the reset message is logged for testing.
