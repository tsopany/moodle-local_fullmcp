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

## License

GPL v3 or later, see [LICENSE](LICENSE).
