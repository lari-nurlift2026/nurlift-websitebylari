NURLIFT / DROPPER — QA EXECUTION RULES

1. Never edit, commit, or push directly to the main branch.

2. Every QA task must be executed in its own branch using:
   qa/<QA_ID>-<short-slug>

3. Before making any change:
   - Read the QA_ID.
   - Confirm the affected page/file.
   - Confirm that the issue described in QA_FEEDBACK actually exists in the current code.
   - Confirm that EXPECTED_RESULT can be implemented unambiguously.

4. Make only the change explicitly requested by the QA task.
   Do not refactor, rewrite, redesign, optimize, or modify unrelated code.

5. Preserve existing:
   - layout
   - CSS
   - JavaScript
   - links
   - SEO metadata
   - analytics/tracking
   unless the QA task explicitly requests a change to them.

6. If the requested issue cannot be found, is already fixed, conflicts with the current code, or is ambiguous:
   STOP.
   Do not modify the code.
   Report the task as BLOCKED and explain why.

7. After making a change:
   - inspect the git diff
   - verify that only the requested change was made
   - perform any reasonable validation available for the project
   - report the files changed

8. Never merge a pull request.
   Human approval is required before merge.

9. Never deploy or modify the production/Plesk environment.

10. For every successfully executed QA task, return:
    QA_ID
    STATUS
    BRANCH
    FILES_CHANGED
    CHANGE_SUMMARY
    VALIDATION
    PR

11. For blocked tasks, return:
    QA_ID
    STATUS: BLOCKED
    BLOCK_REASON
