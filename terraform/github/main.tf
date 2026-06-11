terraform {
  required_providers {
    github = {
      source  = "integrations/github"
      version = "~> 6.0"
    }
  }
}

provider "github" {
  token = var.github_token
  owner = var.github_owner
}

# ── Configuración del repositorio ──────────────────────────────────────────
resource "github_repository" "tick_system" {
  name         = var.repository_name
  description  = "Sistema de Reporte de Incidencias de Infraestructura — INCIDEX"
  visibility   = "public"
  homepage_url = "https://tick-system-remoto-main-r7hja3.free.laravel.cloud/"

  has_issues   = true
  has_projects = true
  has_wiki     = true

  delete_branch_on_merge = true
}

# ── Branch protection: main ─────────────────────────────────────────────────
resource "github_branch_protection" "main" {
  repository_id = github_repository.tick_system.node_id
  pattern       = "main"

  required_status_checks {
    strict   = true
    contexts = ["🔍 Lint & Code Quality", "🐳 Build & Push Docker Image"]
  }

  enforce_admins      = false
  allows_force_pushes = true
  allows_deletions    = false
}

# ── Branch protection: develop ──────────────────────────────────────────────
resource "github_branch_protection" "develop" {
  repository_id = github_repository.tick_system.node_id
  pattern       = "develop"

  required_status_checks {
    strict   = true
    contexts = ["🔍 Lint & Code Quality"]
  }

  enforce_admins      = false
  allows_force_pushes = true
  allows_deletions    = false
}