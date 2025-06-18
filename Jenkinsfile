pipeline {
    agent any
    
    triggers {
        pollSCM('H/5 * * * *')  // Poll every 5 mins
    }

    stages {
        stage('Checkout') {
            steps {
                echo 'Fetching latest code from GitHub...'
                git branch: 'main', url: 'https://github.com/Sharon1913/Nidhi_Test.git'
            }
        }

        stage('Stop Current Services') {
            steps {
                echo 'Stopping current containers...'
                sh 'docker compose down || true'
            }
        }

        stage('Build nidhi Image') {
            steps {
                echo 'Building nidhi web image...'
                sh 'docker build -t nidhi ./web'
            }
        }

        stage('Start Services') {
            steps {
                echo 'Starting containers using docker-compose...'
                sh '''
                    docker compose up -d
                    sleep 10
                    docker compose ps
                '''
            }
        }

        stage('Verify Deployment') {
            steps {
                echo 'Verifying containers are up...'
                sh '''
                    docker ps | grep -q "mysql-container" && echo "✓ MySQL is running" || (echo "✗ MySQL failed" && exit 1)
                    docker ps | grep -q "nidhi" && echo "✓ Web is running" || (echo "✗ Web failed" && exit 1)
                '''
            }
        }
    }

    post {
        success {
            echo 'Deployment successful!'
        }
        failure {
            echo 'Deployment failed. Restarting services...'
            sh 'docker compose up -d || true'
        }
    }
}
