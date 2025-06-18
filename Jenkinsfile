pipeline {
    agent any
    
    triggers {
        // Check for changes every 5 minutes
        pollSCM('H/5 * * * *')
    }
    
    stages {
        stage('Checkout') {
            steps {
                echo 'Fetching latest code from GitHub...'
                git branch: 'main', url: 'https://github.com/Sharon1913/Nidhi_Test.git'
                // Replace the URL above with your actual GitHub repository URL
            }
        }
        
        stage('Stop Current Services') {
            steps {
                echo 'Stopping current containers...'
                sh '''
                    docker-compose down || true
                '''
            }
        }
        
        stage('Build New Images') {
            steps {
                echo 'Building updated Docker images...'
                sh '''
                    # Remove old images to ensure fresh build
                    docker rmi nidhi mysql-container-export || true
                    
                    # Build new images
                    docker build -t nidhi ./web
                    docker build -t mysql-container-export ./db
                '''
            }
        }
        
        stage('Start Services') {
            steps {
                echo 'Starting services with docker-compose...'
                sh '''
                    docker-compose up -d
                    
                    # Wait a bit for services to start
                    sleep 20
                    
                    # Show running containers
                    docker-compose ps
                '''
            }
        }
        
        stage('Verify Deployment') {
            steps {
                echo 'Checking if services are running...'
                sh '''
                    # Check if containers are running
                    if docker ps | grep -q "mysql-container"; then
                        echo "✓ MySQL container is running"
                    else
                        echo "✗ MySQL container failed to start"
                        exit 1
                    fi
                    
                    if docker ps | grep -q "nidhi"; then
                        echo "✓ Web container is running"
                    else
                        echo "✗ Web container failed to start"
                        exit 1
                    fi
                    
                    echo "Deployment successful!"
                '''
            }
        }
    }
    
    post {
        success {
            echo 'Pipeline completed successfully! Your application is now updated and running.'
        }
        failure {
            echo 'Pipeline failed! Attempting to restart previous version...'
            sh 'docker-compose up -d || true'
        }
    }
}