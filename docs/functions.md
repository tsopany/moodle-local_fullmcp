# local_fullmcp web service functions

Clean-room contract. Names/params/returns mirror the `moodle-mcp-bridge` 1.7.0
tool wrappers (`local_mcpbridge_*` -> `local_fullmcp_*`). All functions: `type=write`,
`ajax=true`, run `validate_context()` on the target course/module context, plus
the capabilities listed. Service: "Full MCP Service" (shortname `local_fullmcp`).

Return shapes: the wrappers only state them in prose ("cmid and instance id").
Key names below are our choice where the wrapper doesn't read them; keys the
wrapper reads (`cmid`, `sectionnum`, `content_base64`, `size`, `filename`) are fixed.

`visible`: 1 show / 0 hide. `section`: section NUMBER (0 = general).

## Sections

| Function | Params (default) | Returns | Capability |
|---|---|---|---|
| `create_section` | courseid, name, summary='', position=0 (0 = append) | `sectionid`, `sectionnum` | `moodle/course:update` |
| `update_section` | courseid, sectionnum, name='', summary='', visible=-1 | `sectionid`, `sectionnum` | `moodle/course:update` (+`moodle/course:sectionvisibility` if visible changes) |
| `delete_section` | courseid, sectionnum (>=1) | `success` | `moodle/course:update` (+ deleting the section's activities needs `moodle/course:manageactivities`) |

`update_section` is new (task scope says create/update/delete; wrapper has no update).

## Activities

| Function | Params (default) | Returns | Capability |
|---|---|---|---|
| `create_page` | courseid, section=0, name, content, intro='', visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/page:addinstance` |
| `create_label` | courseid, section=0, content, name='', visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/label:addinstance` |
| `create_url` | courseid, section=0, name, externalurl, intro='', display=0, visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/url:addinstance` |
| `create_book` | courseid, section=0, name, intro='', chaptertitle, chaptercontent, visible=1 | `cmid`, `instanceid`, `chapterid` | `moodle/course:manageactivities`, `mod/book:addinstance`, `mod/book:edit` |
| `create_forum` | courseid, section=0, name, intro='', type='general' (general\|eachuser\|single\|qanda\|blog), visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/forum:addinstance` |
| `create_choice` | courseid, section=0, name, question, options=[str] (>=2), allowupdate=1, visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/choice:addinstance` |
| `create_assignment` | courseid, section=0, name, intro='', duedate=0 (unix ts), grade=100, visible=1; enables online-text + file submissions, feedback comments | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/assign:addinstance` |
| `update_page` | cmid, content, intro='' (empty = keep) | `cmid`, `success` | `moodle/course:manageactivities` |
| `update_activity` | cmid, name='' (keep), visible=-1 (keep) | `cmid`, `name`, `visible` (resulting state) | `moodle/course:manageactivities`; visibility: `moodle/course:activityvisibility` |
| `move_activity` | cmid, section | `cmid`, `section` | `moodle/course:manageactivities` |
| `delete_activity` | cmid | `cmid`, `success` | `moodle/course:manageactivities` (irreversible; uses `course_delete_module`) |

## Quizzes

`quizcmid` = cmid of the quiz. Questions are created in the quiz's course context
question bank and added to the quiz with `defaultmark` as the slot mark.
Returns for all `add_*`: `questionid`, `slot`.
Capabilities for all `add_*`: `mod/quiz:manage` (module ctx) + `moodle/question:add` (question bank ctx).

| Function | Params (default) | Returns | Capability |
|---|---|---|---|
| `create_quiz` | courseid, section=0, name, intro='', timeopen=0, timeclose=0, timelimit=0 (secs), grade=100.0, attempts=0 (unlimited), visible=1 | `cmid`, `instanceid` | `moodle/course:manageactivities`, `mod/quiz:addinstance` |
| `add_quiz_question` (multichoice) | quizcmid, name, questiontext, answers=[{text, fraction, feedback=''}] (>=2), single=1, defaultmark=1.0 | `questionid`, `slot` | see above |
| `add_truefalse_question` | quizcmid, name, questiontext, correctanswer (1/0), defaultmark=1.0 | `questionid`, `slot` | see above |
| `add_shortanswer_question` | quizcmid, name, questiontext, answers=[str] (all full credit), usecase=0, defaultmark=1.0 | `questionid`, `slot` | see above |
| `add_essay_question` | quizcmid, name, questiontext, graderinfo='', responsetemplate='', responselines=15, attachments=0 (-1 unlimited), defaultmark=1.0 | `questionid`, `slot` | see above |
| `add_numerical_question` | quizcmid, name, questiontext, answers=[{answer, tolerance, fraction, feedback=''}], defaultmark=1.0 | `questionid`, `slot` | see above |
| `add_matching_question` | quizcmid, name, questiontext, pairs=[{question, answer}] (>=2), shuffleanswers=1, defaultmark=1.0 | `questionid`, `slot` | see above |

Book chapters: Moodle has no chapter-creation API (core's own `mod/book/edit.php` inserts
into `book_chapters` directly), so `create_book` does the same and fires the core
`chapter_created` event. This is the one deliberate raw write; everything else goes through
Moodle APIs.

## Backup / restore

| Function | Params (default) | Returns | Capability |
|---|---|---|---|
| `backup_course` | courseid, includeusers=0 | `filename`, `size` (bytes), `content_base64` | `moodle/backup:backupcourse` (+`moodle/backup:userinfo` if includeusers=1) |
| `restore_course` | content_base64, categoryid=1, fullname='', shortname='' | `courseid`, `fullname`, `shortname` | `moodle/restore:restorecourse` + `moodle/course:create` in the category context |

**Transfer decision: base64 in the JSON payload** (what the wrapper already does).
- Pros: one round trip, no extra token handling, Python server unchanged.
- Cons: ~33% size overhead, whole file in PHP memory. Mitigation: `backup_course` refuses courses whose .mbz exceeds 100 MB (contract); `restore_course` rejects payloads over the same limit before decoding.
- Alternatives (draft file area upload / pluginfile download with token) rejected for v1: more calls and the wrapper would need rewriting. Revisit if 100 MB proves too small.
- Deployment: a 100 MB backup is ~134 MB of base64 in one POST, so the web server must allow it: nginx `client_max_body_size` and PHP `post_max_size` of at least 140M, and `memory_limit` of 512M or more. Without this, large restores fail at the web server (HTTP 413, seen in testing) before the plugin runs. The plugin's own 100 MB check is a second line of defence.
- Users: `includeusers=1` needs `moodle/backup:userinfo`. On restore, users in the backup are only restored when the caller holds `moodle/restore:userinfo`; the restore never needs the caller to be able to change names, because creating a course lets you choose them.
- Backup runs synchronously, content-only by default (no users, no logs), temp file deleted after reading. Restore always makes a new course (visibility as in the backup), deletes its temp files, and fails if `shortname` already exists. If a restore fails after the course row was created, the half-made course is deleted.

## Core functions also in the service (v1)

The service also holds 14 core Moodle functions, so the MCP server can read course
structure and build courses. Decision for v1: **no tool that reads or changes other
people's personal data is exposed**, and nothing destructive on users or courses.

| Kind | Functions |
|---|---|
| Reads | `core_webservice_get_site_info`, `core_course_get_courses`, `core_course_get_courses_by_field`, `core_course_get_contents`, `core_calendar_get_action_events_by_timesort`, `core_competency_list_course_competencies`, `mod_assign_get_assignments`, `mod_forum_get_forums_by_courses`, `mod_quiz_get_quizzes_by_courses` |
| Writes | `core_course_create_courses`, `core_course_update_courses`, `core_course_create_categories`, `core_course_import_course`, `core_files_upload` |

Deliberately left out: user create/update/delete, role assign/unassign, enrol/unenrol,
cohorts, groups, messaging, grades and grading, submissions, quiz attempts and answers,
completion per user, notifications, learning plans, enrolled-user lists, forum
discussions and posts, and deleting courses or categories. `tests/service_test.php`
fails if any of these is added to the service.

`build_course` in the Python server uses `core_course_delete_courses` to roll back; that call
is refused by the service, so a failed build leaves the half-built course in place.

Later, a second service (own token, own user with wider permissions) could hold the
personal-data reads for use with local models only.

The service user also needs, for `core_course_update_courses`:
`moodle/course:changefullname`, `moodle/course:changeshortname`, `moodle/course:changesummary`,
`moodle/course:visibility`; and for categories `moodle/category:manage`.

## Capability checks (design)

Every function: `external_api::validate_parameters` -> resolve the target
(course / cm) -> `self::validate_context($context)` -> `require_capability`.
Targets resolve via `get_course`, `get_coursemodule_from_id(..., MUST_EXIST)` and
`context_module::instance`; never trust an id without loading its course first.

| Operation | Context | Required |
|---|---|---|
| Create section / update section | course | `moodle/course:update` |
| Change section visibility | course | `moodle/course:sectionvisibility` |
| Delete section | course | `moodle/course:update`; per deleted activity `moodle/course:manageactivities` |
| Create any activity | course | `moodle/course:manageactivities` + `mod/<type>:addinstance` |
| Set activity hidden/visible | module | `moodle/course:activityvisibility` |
| Rename / move / update_page / delete activity | module | `moodle/course:manageactivities` |
| Book chapter | module | `mod/book:edit` |
| Quiz questions | module + question bank ctx | `mod/quiz:manage` + `moodle/question:add` |
| Backup | course | `moodle/backup:backupcourse`; with users: `moodle/backup:userinfo` |
| Restore | category | `moodle/restore:restorecourse` + `moodle/course:create`; restoring into new course never touches other courses |

The plugin defines no capabilities of its own (`db/access.php` not needed), so
site admins can scope the service user with standard roles. `visible` changes
are only attempted when requested; creating a hidden activity needs
`moodle/course:activityvisibility` too.

### Service-user role (minimal, not admin)

Assign at system level (or a course-creator category) a custom role "MCP builder" allowing:

- `moodle/course:create`, `moodle/restore:restorecourse`, `moodle/backup:backupcourse`
- `moodle/course:update`, `moodle/course:manageactivities`, `moodle/course:activityvisibility`, `moodle/course:sectionvisibility`
- `mod/page:addinstance`, `mod/label:addinstance`, `mod/url:addinstance`, `mod/quiz:addinstance`, `mod/book:addinstance`, `mod/forum:addinstance`, `mod/choice:addinstance`, `mod/assign:addinstance`
- `mod/book:edit`, `mod/quiz:manage`, `moodle/question:add`
- `moodle/course:view`, `moodle/course:viewhiddenactivities`, `moodle/course:viewhiddensections`, and the view capability of each type above (`mod/<type>:view`, but `mod/book:read`, `mod/forum:viewdiscussion`, `mod/choice:choose`) (found in testing: without `mod/quiz:view` the quiz counts as inaccessible and every quiz call fails with "Course or activity not accessible")
- plus `webservice/rest:use`, and the user added to the "Full MCP Service" authorised users.

Explicitly NOT granted: `moodle/site:config`, user management, `moodle/backup:userinfo`
(unless user-data backups are wanted), `moodle/course:delete`.
