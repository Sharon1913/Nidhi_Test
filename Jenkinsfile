pipeline {
    agent any

    environment {
        // Define your application directory
        APP_DIR = '/home/nidhi'
        // Define compose file if it's not the default name
        COMPOSE_FILE = 'docker-compose.yml'
        // MySQL credentials (should match your docker-compose.yml)
        MYSQL_ROOT_PASSWORD = 'my-secret-pw'
        // Docker compose project name for better isolation
        COMPOSE_PROJECT_NAME = 'nidhi-app'
    }

    stages {
        stage('Pull latest code') {
            steps {
                echo 'Pulling latest code from Git'
                dir(env.APP_DIR) {
                    // Ensure we're on the correct branch and pull latest
                    sh '''
                        git fetch origin
                        git checkout rel-code
                        git pull origin rel-code
                    '''
                }
            }
        }

        stage('Pre-deployment checks') {
            steps {
                echo 'Running pre-deployment checks'
                dir(env.APP_DIR) {
                    // Check if docker-compose file exists
                    sh 'test -f docker-compose.yml || (echo "docker-compose.yml not found" && exit 1)'
                    
                    // Check if required Docker images exist
                    sh '''
                        echo "Checking if required Docker images exist..."
                        docker images | grep -q "mysql-container-export" || echo "Warning: mysql-container-export image not found"
                        docker images | grep -q "nidhi" || echo "Warning: nidhi image not found"
                    '''
                    
                    // Check if containers are running and note current state
                    sh 'docker compose ps || true'
                    
                    // Check if port 8080 is available (if not running containers)
                    sh '''
                        if ! docker compose ps | grep -q "Up"; then
                            if netstat -tulpn | grep -q ":8080"; then
                                echo "Warning: Port 8080 is already in use"
                                netstat -tulpn | grep ":8080"
                            fi
                        fi
                    '''
                    
                    // Optional: Run syntax check on PHP files
                    sh '''
                        echo "Checking PHP syntax..."
                        find . -name "*.php" -exec php -l {} \\; 2>/dev/null || echo "PHP syntax check completed with warnings/errors"
                    '''
                }
            }
        }

        stage('Backup current state') {
            steps {
                echo 'Creating backup of current deployment'
                dir(env.APP_DIR) {
                    script {
                        // Create backup of database if MySQL container is running
                        sh '''
                            echo "Creating database backup..."
                            BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
                            mkdir -p backups
                            
                            # Check if MySQL container is running
                            if docker compose ps mysql | grep -q "Up"; then
                                echo "MySQL container is running, creating backup..."
                                # Use the exact container name from your compose file
                                docker exec mysql-container mysqldump -u root -p${MYSQL_ROOT_PASSWORD} --all-databases > backups/backup_${BACKUP_DATE}.sql 2>/dev/null || {
                                    echo "Database backup failed, but continuing deployment..."
                                }
                            else
                                echo "MySQL container not running, skipping database backup"
                            fi
                            
                            # Also backup the current docker-compose.yml
                            cp docker-compose.yml backups/docker-compose_${BACKUP_DATE}.yml || true
                        '''
                    }
                }
            }
        }

        stage('Deploy with Docker Compose') {
            steps {
                echo 'Deploying using Docker Compose'
                dir(env.APP_DIR) {
                    script {
                        try {
                            // Stop containers gracefully with longer timeout
                            sh 'docker compose down --timeout 60'
                            
                            // Clean up unused images and containers to free space
                            sh '''
                                docker image prune -f
                                docker container prune -f
                            '''
                            
                            // Build and start containers with project name
                            sh '''
                                export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME}
                                docker compose up -d --build --remove-orphans --force-recreate
                            '''
                            
                            echo 'Containers started, waiting for services to be ready...'
                            
                        } catch (Exception e) {
                            echo "Deployment failed: ${e.getMessage()}"
                            // Show logs for debugging
                            sh 'docker compose logs --tail=20 || true'
                            throw e
                        }
                    }
                }
            }
        }

        stage('Post-deployment verification') {
            steps {
                echo 'Verifying deployment'
                dir(env.APP_DIR) {
                    script {
                        // Wait longer for containers to be ready
                        sh 'sleep 15'
                        
                        // Check if containers are running
                        sh '''
                            echo "Checking container status..."
                            docker compose ps
                            
                            # Check if containers are healthy/running
                            MYSQL_STATUS=$(docker compose ps mysql --format "{{.Status}}" | grep -c "Up" || echo "0")
                            WEB_STATUS=$(docker compose ps web --format "{{.Status}}" | grep -c "Up" || echo "0")
                            
                            if [ "$MYSQL_STATUS" -eq 0 ]; then
                                echo "MySQL container is not running!"
                                docker compose logs mysql --tail=10
                                exit 1
                            fi
                            
                            if [ "$WEB_STATUS" -eq 0 ]; then
                                echo "Web container is not running!"
                                docker compose logs web --tail=10
                                exit 1
                            fi
                            
                            echo "All containers are running successfully"
                        '''
                        
                        // MySQL connectivity check
                        sh '''
                            echo "Testing MySQL connectivity..."
                            for i in {1..10}; do
                                if docker exec mysql-container mysqladmin ping -u root -p${MYSQL_ROOT_PASSWORD} --silent; then
                                    echo "MySQL is ready and responding"
                                    break
                                else
                                    echo "Attempt $i: MySQL not ready, waiting..."
                                    sleep 5
                                fi
                                
                                if [ $i -eq 10 ]; then
                                    echo "MySQL connectivity check failed after 10 attempts"
                                    docker compose logs mysql --tail=10
                                    exit 1
                                fi
                            done
                        '''
                        
                        // Web application health check
                        sh '''
                            echo "Performing web application health check..."
                            
                            # Test if web service is responding on port 8080
                            for i in {1..8}; do
                                if curl -f -s --connect-timeout 5 http://localhost:8080 > /dev/null; then
                                    echo "Web application is responding correctly on port 8080"
                                    break
                                else
                                    echo "Attempt $i: Web application not responding, waiting..."
                                    sleep 10
                                fi
                                
                                if [ $i -eq 8 ]; then
                                    echo "Web application health check failed after 8 attempts"
                                    echo "Checking web container logs:"
                                    docker compose logs web --tail=15
                                    exit 1
                                fi
                            done
                        '''
                    }
                }
            }
        }

        stage('Cleanup') {
            steps {
                echo 'Cleaning up old resources'
                dir(env.APP_DIR) {
                    // Remove old/unused Docker images and volumes
                    sh '''
                        docker image prune -f
                        docker volume prune -f || true
                    '''
                    
                    // Clean up old backups (keep only last 10 days)
                    sh '''
                        if [ -d "backups" ]; then
                            find backups -name "backup_*.sql" -mtime +10 -delete || true
                            find backups -name "docker-compose_*.yml" -mtime +10 -delete || true
                            echo "Old backups cleaned up (keeping last 10 days)"
                        fi
                    '''
                }
            }
        }
    }

    post {
        always {
            echo 'Pipeline execution completed'
            dir(env.APP_DIR) {
                // Always show final container status
                sh '''
                    echo "=== Final Container Status ==="
                    docker compose ps || true
                    echo "=== Docker System Info ==="
                    docker system df || true
                '''
            }
        }
        
        success {
            echo 'Deployment successful! 🎉'
            dir(env.APP_DIR) {
                sh '''
                    echo "=== Deployment Summary ==="
                    echo "Web Application: http://localhost:8080"
                    echo "MySQL Database: localhost:3306"
                    echo "Database: tihan_project_management"
                    docker compose ps --format "table {{.Name}}\\t{{.Status}}\\t{{.Ports}}"
                '''
            }
            // You can add notifications here (email, Slack, etc.)
        }
        
        failure {
            echo 'Deployment failed! 🚨'
            dir(env.APP_DIR) {
                // Show detailed container logs for debugging
                sh '''
                    echo "=== Container Logs for Debugging ==="
                    echo "--- MySQL Logs ---"
                    docker compose logs mysql --tail=30 || true
                    echo "--- Web Logs ---"
                    docker compose logs web --tail=30 || true
                    echo "--- System Resources ---"
                    df -h || true
                    docker system df || true
                '''
                
                // Attempt to restart services
                sh '''
                    echo "Attempting to restart services..."
                    docker compose down || true
                    sleep 5
                    docker compose up -d || true
                '''
            }
        }
        
        unstable {
            echo 'Deployment completed with warnings'
            dir(env.APP_DIR) {
                sh 'docker compose logs --tail=20 || true'
            }
        }
    }
}