pipeline {
    agent any

    triggers {
        pollSCM('H/5 * * * *') // Poll SCM every 5 minutes
    }

    stages {
        stage('Checkout') {
            steps {
                echo 'Fetching latest code from GitHub...'
                git branch: 'main', url: 'https://github.com/Sharon1913/Nidhi_Test.git'
            }
        }

        stage('Verify Docker') {
            steps {
                echo 'Verifying Docker installation...'
                sh 'docker --version'
                sh 'docker-compose --version'
            }
        }

        stage('Stop Current Containers') {
            steps {
                echo 'Stopping running containers...'
                sh 'docker-compose down || true'
            }
        }
    
        stage('Build nidhi Image') {
            steps {
                echo 'Rebuilding nidhi image from updated code...'
                sh 'docker build -t nidhi .'
            }
        }

        stage('Start Containers') {
            steps {
                echo 'Starting services with docker-compose...'
                sh 'docker-compose up -d'
            }
        }

        stage('Health Check') {
            steps {
                echo 'Checking container health...'
                sh 'docker ps'
                sh 'sleep 10' // Wait for services to start
                sh 'curl -f http://localhost:8080 || echo "Service not ready yet"'
            }
        }
    }

    post {
        success {
            echo 'Deployment complete!'
            sh 'docker ps --format "table {{.Names}}\\t{{.Status}}\\t{{.Ports}}"'
        }
        failure {
            echo 'Deployment failed. Attempting recovery...'
            sh 'docker-compose logs'
            sh 'docker-compose up -d || true'
        }
        always {
            echo 'Cleaning up unused Docker images...'
            sh 'docker image prune -f || true'
        }
    }
}