# Audit Findings: bedrock-cli
_Last updated: 2026-10-08 18:37_

## Open Findings

### P1

- **[#1]** Memory Heap Exhaustion and Resource Leaks in CloneCommand Database Streaming
  - CloneCommand accumulates stdout in Symfony Process memory during mysqldump without calling clearOutput, and lacks RAII try-finally blocks on file descriptors.
  - _Found: 2026-10-08 18:37_

- **[#2]** Insecure Subprocess Tar Stream and Unescaped Remote Paths in CloneCommand
  - CloneCommand invokes popen with shell strings for tar streaming without RAII, and executes SSH cat/tar without escaping remote path parameters.
  - _Found: 2026-10-08 18:37_

- **[#3]** Missing TLS Verification in OrderCommand and Undefined Key Variable in ImportCoreCommand
  - OrderCommand activates plugins over HTTP without verifying SSL certificates/hostnames, and ImportCoreCommand accesses undefined variable key during config import.
  - _Found: 2026-10-08 18:37_
