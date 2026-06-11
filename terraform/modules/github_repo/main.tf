# ── Módulo reutilizable: github_repo ───────────────────────────────────────
# Este módulo encapsula la configuración estándar de un repositorio GitHub
# y sus branch protections. Puede instanciarse para cualquier repo del equipo.

variable "repository_name"  { type = string }
variable "description"       { type = string }
variable "homepage_url"      { type = string  default = "" }
variable "protected_branches" {
  type    = list(string)
  default = ["main", "develop"]
}

resource "github_repository" "repo" {
  name                   = var.repository_name
  description            = var.description
  visibility             = "public"
  homepage_url           = var.homepage_url
  has_issues             = true
  has_projects           = true
  has_wiki               = true
  delete_branch_on_merge = true
}

resource "github_branch_protection" "protection" {
  for_each      = toset(var.protected_branches)
  repository_id = github_repository.repo.node_id
  pattern       = each.value

  allows_force_pushes = true
  allows_deletions    = false
  enforce_admins      = false
}

output "repository_url" {
  value = github_repository.repo.html_url
}

output "node_id" {
  value = github_repository.repo.node_id
}