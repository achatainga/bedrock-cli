# Audit Findings: bedrock-cli
_Last updated: 2026-10-08 18:59_

## Open Findings

### P1

- **[#4]** Unparameterized Shell Invocations in Acorn, Reinstall, and Info Commands
  - Multiple commands use Process::fromShellCommandline and OS shells (rmdir, rm -rf) instead of structured argument arrays and Symfony Filesystem.
  - _Found: 2026-10-08 18:59_

- **[#5]** Unbounded wpdb options Query Without SRE Limit Guardrail
  - PullCommand and ExportConfigCommand query options table without LIMIT guardrails, risking container OOM on large option tables.
  - _Found: 2026-10-08 18:59_

- **[#6]** Path Traversal Defense in Depth in RestController findPluginFile
  - RestController findPluginFile directly concatenates plugin slug with WP_PLUGIN_DIR without filtering directory traversal characters.
  - _Found: 2026-10-08 18:59_

## Resolved Findings

- ~~**[#1]** Memory Heap Exhaustion and Resource Leaks in CloneCommand Database Streaming~~ (commit: `50cddda`)
  - _Resolved: 2026-10-08 18:58_

- ~~**[#2]** Insecure Subprocess Tar Stream and Unescaped Remote Paths in CloneCommand~~ (commit: `50cddda`)
  - _Resolved: 2026-10-08 18:58_

- ~~**[#3]** Missing TLS Verification in OrderCommand and Undefined Key Variable in ImportCoreCommand~~ (commit: `50cddda`)
  - _Resolved: 2026-10-08 18:58_
