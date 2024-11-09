=== WPIssueTracker ===
Contributors: mizou1255
Tags: issue tracking, support tickets, customer service, project management, WordPress
Requires at least: 6.2
Tested up to: 6.6.2
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

📋 A professional issue tracking and support ticket management plugin for WordPress. Easily manage and prioritize customer support tickets directly from your WordPress dashboard.

== Description ==

**WPIssueTracker** is a robust and user-friendly issue tracking and support ticket plugin designed for WordPress. It allows you to create, manage, and prioritize tickets directly in your WordPress dashboard, ideal for customer support or project management.

✨ = Key Features =
* **Ticket Management**: Easily create, update, and manage support tickets with titles, descriptions, and priority levels.
* **Priority Levels**: Set customizable ticket priorities (e.g., Low, Medium, High).
* **User Access Control**: Manage permissions for different user roles.
* **File Attachments** 📎: Allow file uploads for each ticket, stored securely in a custom upload directory.
* **Email Notifications** 📧: Send customizable notifications to admins and users when tickets are created or updated.
* **Comment System** 💬: Facilitate ticket discussions through a built-in commenting system.
* **Customizable Statuses** 🔄: Track ticket statuses such as "New," "In Progress," "Resolved," etc.

== Installation ==

1. 📂 Upload the `wpissuetracker` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. 🔌 Activate the plugin through the 'Plugins' menu in WordPress.
3. ⚙️ Go to **Settings > WPIssueTracker** to configure your settings, priorities, and email notifications.

== Frequently Asked Questions ==

= ❓ How do I set up email notifications? =
Navigate to **Settings > WPIssueTracker > Email Notifications** to enable notifications for admins and users. You can also customize the subject and message content of these notifications.

= 🔒 Can I restrict ticket management to certain user roles? =
Yes. Go to **Settings > WPIssueTracker > User Roles** to define which roles have access to ticket management functionalities.

= 🔧 How do I add custom priority levels? =
Go to **Settings > WPIssueTracker > Priorities** to add, edit, or delete priority levels.

= 📎 What types of files can users upload? =
Only JPEG, PNG, and PDF file types are allowed for upload. Files are sanitized and stored in a custom directory for security.

== Screenshots ==

1. ⚙️ **Settings Screen** - Configure plugin settings, email notifications, and role access.
2. 📝 **Ticket List** - View all tickets, with indicators for priority and status.
3. 🔍 **Ticket Details** - Detailed view of each ticket, including comments and attachments.
4. 📊 **Priorities Management** - Manage ticket priority levels.

== Changelog ==

= 1.0.0 =
* 🎉 Initial release with ticket management, priority levels, email notifications, and role-based access control.

== Upgrade Notice ==

= 1.0.0 =
First stable release of WPIssueTracker.

== REST API Endpoints ==

* 🆕 `POST /wp-json/wpissuetracker/v1/create-ticket` - Create a new support ticket.
* 📄 `GET /wp-json/wpissuetracker/v1/tickets` - Retrieve all support tickets.
* 🔄 `POST /wp-json/wpissuetracker/v1/update-ticket-status/{id}` - Update the status of a ticket.

== Developer Notes ==

The plugin creates three database tables:
1. **Tickets Table** (`wpit_tickets`) - Stores ticket details (title, description, priority, status, etc.).
2. **Comments Table** (`wpit_comments`) - Stores comments associated with tickets.
3. **Priorities Table** (`wpit_priorities`) - Manages priority levels.

== Security & Sanitization ==

* **Nonce Verification** 🔑: All REST API requests require a nonce for verification.
* **Sanitization** 🧼: All user inputs are sanitized before being stored.
* **File Validation** 📂: File uploads are restricted to specific types, and file names are sanitized.

== Custom Upload Directory ==

Uploaded files are stored in `/wp-content/uploads/wpit-uploads/`. This directory is automatically created on plugin activation and deleted upon plugin deactivation.

== Support ==

For support, please use the [Support Forum](https://wordpress.org/support/plugin/wpissuetracker) or contact us at contact@melioze.com.

