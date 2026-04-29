def runCommand(String unixCommand, String windowsCommand) {
    if (isUnix()) {
        sh unixCommand
    } else {
        bat windowsCommand
    }
}

pipeline {
    agent any

    options {
        timestamps()
        ansiColor('xterm')
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '20'))
        timeout(time: 30, unit: 'MINUTES')
    }

    parameters {
        booleanParam(name: 'SKIP_FRONTEND', defaultValue: false, description: 'Si es true, omite npm ci y npm run build')
    }

    environment {
        APP_ENV = 'testing'
        COMPOSER_ALLOW_SUPERUSER = '1'
        COMPOSER_NO_INTERACTION = '1'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Toolchain') {
            steps {
                script {
                    runCommand('php -v', 'php -v')
                    runCommand('composer --version', 'composer --version')
                    runCommand('node -v', 'node -v')
                    runCommand('npm -v', 'npm -v')
                }
            }
        }

        stage('Install Backend Dependencies') {
            steps {
                script {
                    runCommand(
                        'composer install --prefer-dist --no-progress --optimize-autoloader',
                        'composer install --prefer-dist --no-progress --optimize-autoloader'
                    )
                }
            }
        }

        stage('Prepare Environment') {
            steps {
                script {
                    runCommand('cp .env.example .env', 'copy /Y .env.example .env')
                    runCommand('php artisan key:generate --force', 'php artisan key:generate --force')
                }
            }
        }

        stage('Run PHPUnit') {
            steps {
                script {
                    runCommand('mkdir -p storage/test-results', 'if not exist storage\\test-results mkdir storage\\test-results')
                    runCommand('vendor/bin/phpunit --log-junit storage/test-results/phpunit.xml', 'vendor\\bin\\phpunit --log-junit storage\\test-results\\phpunit.xml')
                }
            }
        }

        stage('Build Frontend') {
            when {
                expression { !params.SKIP_FRONTEND }
            }
            steps {
                script {
                    runCommand('npm ci', 'npm ci')
                    runCommand('npm run build', 'npm run build')
                }
            }
        }
    }

    post {
        always {
            junit allowEmptyResults: true, testResults: 'storage/test-results/phpunit.xml'
            archiveArtifacts artifacts: 'public/build/**', allowEmptyArchive: true
        }
    }
}
