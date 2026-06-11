output "repository_url" {
  description = "URL del repositorio"
  value       = github_repository.tick_system.html_url
}

output "repository_node_id" {
  description = "Node ID del repositorio (usado internamente por Terraform)"
  value       = github_repository.tick_system.node_id
}

output "main_protection_id" {
  description = "ID de la branch protection de main"
  value       = github_branch_protection.main.id
}

output "develop_protection_id" {
  description = "ID de la branch protection de develop"
  value       = github_branch_protection.develop.id
}