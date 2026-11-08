# local_fullmcp

Moodle `local` plugin: web service functions for course and content management
(sections, activities, quizzes and questions, course backup/restore), for use by
an MCP server. Fills the gap in core web services, which cannot create or edit
activities.

- Moodle 5.3+ (plugin installs at `public/local/fullmcp`; the folder must be named `fullmcp`).
- Function list, parameters, returns and capabilities: [docs/functions.md](docs/functions.md).
- Uses Moodle APIs only (`add_moduleinfo()`, `update_moduleinfo()`, `course_delete_module()`, backup/restore controllers, question bank APIs).

## Setup

1. Install the plugin and run the upgrade.
2. Enable web services and the REST protocol; enable the "Full MCP Service".
3. Create a service user with a minimal role (see docs/functions.md), add it to the service, create a token.

## Tests

PHPUnit tests are in `tests/`. Run them in a Moodle checkout with the plugin installed and PHPUnit initialised:

    vendor/bin/phpunit --testsuite local_fullmcp_testsuite

The throwaway dev site in `dev/` can be used for this (`composer install` plus `admin/tool/phpunit/cli/init.php`).

## Backup and restore size

`backup_course` and `restore_course` move the .mbz as base64 inside the request (max 100 MB).
Set nginx `client_max_body_size` and PHP `post_max_size` to at least 140M and `memory_limit` to 512M or more.

## License

GPL v3 or later, see [LICENSE](LICENSE).
