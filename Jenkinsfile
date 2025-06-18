pipeline {
    agent any

    stages {
        stage('Pull latest code') {
            steps {
                echo 'Pulling latest code from Git'
                // This happens automatically when using "Pipeline script from SCM"
            }
        }

        stage('Deploy with Docker Compose') {
            steps {
                echo 'Deploying using Docker Compose'
                dir('/home/nidhi') {
                    sh 'docker compose down'
                    sh 'docker compose up -d --build'
                }
            }
        }
    }
}
