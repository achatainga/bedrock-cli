# AWS Deployment Report: Bedrock Management Infrastructure

**Date:** December 15, 2025  
**Project:** Bedrock CLI & Bedrock WordPress Architecture  
**Prepared For:** AWS Sales & Solution Architecture Team

## 1. Executive Summary

This report outlines the deployment strategy for the Bedrock CLI ecosystem on Amazon Web Services (AWS). While bedrock-cli is primarily a local development tool, its output (Twelve-Factor WordPress applications) is highly cloud-native compatible.

The proposed architecture shifts the CLI from a local developer tool to a **Cloud Management Orchestrator** hosted within AWS CodeBuild/CodePipeline, governing a high-availability serverless infrastructure for the WordPress sites it provisions.

### Strategic Goals:
- **Modernize Operations**: Move from local docker-compose management to AWS ECS (Elastic Container Service).
- **Enhance Reliability**: Leverage Amazon Aurora and ElastiCache to replace local containers.
- **Security & Compliance**: Utilize AWS Systems Manager for secret management, replacing local .env files.

## 2. Required AWS Services & Configuration

To support a production-grade Bedrock environment managed by this CLI, we recommend the following service stack:

### A. Compute (The Application)
- **Service**: Amazon ECS on AWS Fargate
- **Purpose**: Runs the WordPress PHP-FPM and Nginx containers without managing servers.
- **Configuration**:
  - Task Definition: 1 vCPU, 2 GB Memory (Scale based on traffic).
  - Auto-scaling: Configured to scale between 2 and 10 tasks based on CPU utilization.

### B. Database (The Data)
- **Service**: Amazon RDS for MySQL (or Aurora Serverless v2 for high traffic)
- **SKU**: db.t4g.small (Multi-AZ deployment for production).
- **Purpose**: Persistent storage for WordPress content.
- **Configuration**: MySQL 8.0 engine, encrypted storage at rest.

### C. Caching (Performance)
- **Service**: Amazon ElastiCache for Redis
- **SKU**: cache.t4g.micro
- **Purpose**: Object Cache (supported natively by bedrock-cli via rhubarbgroup/redis-cache).
- **Configuration**: Cluster mode disabled (for simple WP setups), Multi-AZ enabled.

### D. Management & Build (The CLI Home)
- **Service**: AWS CodeBuild & AWS CodePipeline
- **Instance Type**: build.general1.small
- **Purpose**: This is where bedrock-cli will run during deployments. It will execute migrations (bedrock migrate) and asset compilation.

### E. Storage (Assets)
- **Service**: Amazon S3 + Amazon CloudFront
- **Purpose**: Offload wp-content/uploads using the S3-Uploads plugin (standard Bedrock practice) to make containers stateless.

## 3. Estimated Monthly Costs (US East - N. Virginia)

Estimates based on a standard production workload serving ~100k visits/month.

| Service | Configuration | Estimated Cost (USD) |
|---------|--------------|---------------------|
| Amazon ECS (Fargate) | 2 Tasks (Always on), 1 vCPU, 2GB RAM | $73.00 |
| Application Load Balancer | 1 LCU (Load Balancer Capacity Unit) | $22.00 |
| Amazon RDS (MySQL) | db.t4g.small (Multi-AZ), 20GB Storage | $34.00 |
| Amazon ElastiCache | cache.t4g.micro (2 nodes, Multi-AZ) | $24.00 |
| Amazon S3 | 50GB Standard Storage + Data Transfer | $2.50 |
| AWS CloudFront | 100GB Outbound Data Transfer | $8.50 |
| AWS CodeBuild | 100 build minutes/month | $0.50 |
| AWS Secrets Manager | 5 Secrets (DB, Redis, Salts) | $2.00 |
| **Total Estimated Monthly** | | **~$166.50** |

**Note:** Costs can be reduced by ~40% using Savings Plans for Compute and Reserved Instances for RDS.

## 4. Architecture Diagram Description

### 1. The Control Plane (CI/CD):
- Developers push code to GitHub/GitLab. This triggers AWS CodePipeline.
- **Build Stage**: AWS CodeBuild spins up a container. It installs PHP and Composer, then executes bedrock-cli commands (e.g., `bedrock db:migrate`) to update the database schema.
- **Artifacts**: The build process produces a Docker image pushed to Amazon ECR.

### 2. The Data Plane (VPC Private Subnet):
- RDS MySQL and ElastiCache Redis reside here, inaccessible from the public internet.
- ECS Fargate Tasks (running WordPress) connect to these services. They handle PHP processing.

### 3. The Content Plane (Public/Edge):
- Application Load Balancer (ALB) sits in the public subnet, routing HTTPS traffic to Fargate.
- CloudFront caches static assets (images, CSS, JS) served directly from S3, bypassing the PHP application entirely.

## 5. Migration & Refactoring Plan

To migrate the bedrock-cli from a local tool to this AWS architecture, specific refactoring is required:

### Phase 1: Security & Statelessness
- **Refactor Secrets**: Modify `src/Services/Management/ManagementService.php` to read configuration from AWS Systems Manager Parameter Store instead of local .env files.
- **Remove Ephemeral Dependencies**: Replace the Python-based `UnzipService.php` with native PHP implementations to ensure the CLI runs on standard AWS Linux 2023 build images without custom runtime installation.

### Phase 2: Docker Decoupling
- **Abstract Runtime**: The current `DockerService.php` hardcodes `docker-compose exec`. This must be refactored to support AWS ECS Exec (`aws ecs execute-command`).
- **Action**: Create an `EcsRuntime` adapter that translates `bedrock wp plugin list` into an API call to the running Fargate task.
- **S3-Native Backups**: Update `SnapshotCommand.php` to stream SQL dumps directly to an S3 bucket (`s3://my-project-backups/`) instead of the local filesystem.

### Phase 3: Deployment
- **Containerize CLI**: Create a Dockerfile specifically for the CLI tool to run inside CodeBuild.
- **Pipeline Config**: Create a `buildspec.yml` that utilizes `bedrock install` and `bedrock db:migrate` as build steps.

## 6. AWS Support Recommendation

**Recommended Plan:** AWS Business Support

### Justification:
- **Production Workloads**: Since this architecture hosts production CMS systems, 24/7 access to Cloud Support Engineers is critical if the RDS or ECS control plane encounters issues.
- **Infrastructure as Code (IaC) Support**: Business support includes guidance on third-party software interoperability (like Docker and PHP-FPM configurations on Fargate), which is essential given the custom nature of the bedrock-cli orchestration.

## Next Actions:
1. Initiate Proof of Concept (PoC) for bedrock-cli running inside AWS CodeBuild.
2. Refactor `WpCliService.php` to support remote command execution via SSM.
3. Contact AWS Sales team with this report for detailed cost optimization and architecture review.

---

**Project Scores:**
- **Production Readiness**: 60/100
- **AWS Compatibility**: 45/100
- **Recommended**: Refactor security and dependency layers before production deployment

**Generated by:** AURA SENTINEL Protocol Analysis
**Contact:** AWS Solutions Architect Team
