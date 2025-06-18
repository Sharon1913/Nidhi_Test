pipeline {
    agent any

    environment {
        // Define your application directory
        APP_DIR = '/home/nidhi'
        // Define compose file if it's not the default name
        COMPOSE_FILE = 'docker-compose.yml'
    }

    stages {
        stage('Pull latest code') {
            steps {
                echo 'Pulling latest code from Git'
                // This happens automatically when using "Pipeline script from SCM"
                // But we can add a manual pull to ensure we have the latest
                dir(env.APP_DIR) {
                    sh 'git pull origin rel-code'
                }
            }
        }

        stage('Pre-deployment checks') {
            steps {
                echo 'Running pre-deployment checks'
                dir(env.APP_DIR) {
                    // Check if docker-compose file exists
                    sh 'test -f docker-compose.yml || (echo "docker-compose.yml not found" && exit 1)'
                    
                    // Check if containers are running and note current state
                    sh 'docker compose ps || true'
                    
                    // Optional: Run syntax check on PHP files
                    sh '''
                        echo "Checking PHP syntax..."
                        find . -name "*.php" -exec php -l {} \\; || true
                    '''
                }
            }
        }

        stage('Backup current state') {
            steps {
                echo 'Creating backup of current deployment'
                dir(env.APP_DIR) {
                    script {
                        // Create backup of database if needed
                        sh '''
                            echo "Creating database backup..."
                            BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
                            mkdir -p backups
                            
                            # Get MySQL container name dynamically
                            MYSQL_CONTAINER=$(docker compose ps -q mysql 2>/dev/null || docker compose ps -q db 2>/dev/null || echo "")
                            
                            if [ ! -z "$MYSQL_CONTAINER" ]; then
                                echo "Backing up database..."
                                docker compose exec -T mysql mysqldump -u root -p$MYSQL_ROOT_PASSWORD --all-databases > backups/backup_${BACKUP_DATE}.sql || true
                            else
                                echo "No MySQL container found, skipping database backup"
                            fi
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
                            // Stop containers gracefully
                            sh 'docker compose down --timeout 30'
                            
                            // Clean up unused images to free space
                            sh 'docker image prune -f'
                            
                            // Build and start containers
                            sh 'docker compose up -d --build --remove-orphans'
                            
                            echo 'Deployment completed successfully'
                        } catch (Exception e) {
                            echo "Deployment failed: ${e.getMessage()}"
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
                        // Wait for containers to be ready
                        sh 'sleep 10'
                        
                        // Check if containers are running
                        sh '''
                            echo "Checking container status..."
                            docker compose ps
                            
                            # Check if all containers are healthy
                            UNHEALTHY=$(docker compose ps --format "table {{.Service}}\\t{{.Status}}" | grep -v "Up" | grep -v "SERVICE" | wc -l)
                            if [ $UNHEALTHY -gt 0 ]; then
                                echo "Some containers are not running properly!"
                                docker compose ps
                                exit 1
                            fi
                        '''
                        
                        // Optional: Health check for web application
                        sh '''
                            echo "Performing application health check..."
                            # Get the web application port (adjust as needed)
                            WEB_PORT=$(docker compose port web 80 2>/dev/null | cut -d: -f2 || echo "80")
                            
                            # Simple curl check (adjust URL as needed)
                            for i in {1..5}; do
                                if curl -f -s http://localhost:${WEB_PORT} > /dev/null; then
                                    echo "Application is responding correctly"
                                    break
                                else
                                    echo "Attempt $i: Application not responding, waiting..."
                                    sleep 5
                                fi
                                
                                if [ $i -eq 5 ]; then
                                    echo "Application health check failed after 5 attempts"
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
                    // Remove old/unused Docker images
                    sh 'docker image prune -f'
                    
                    // Clean up old backups (keep only last 7 days)
                    sh '''
                        if [ -d "backups" ]; then
                            find backups -name "backup_*.sql" -mtime +7 -delete || true
                            echo "Old backups cleaned up"
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
                sh 'docker compose ps || true'
            }
        }
        
        success {
            echo 'Deployment successful! 🎉'
            // You can add notifications here (email, Slack, etc.)
        }
        
        failure {
            echo 'Deployment failed! 🚨'
            dir(env.APP_DIR) {
                // Show container logs for debugging
                sh '''
                    echo "=== Container Logs ==="
                    docker compose logs --tail=50 || true
                '''
                
                // Optional: Rollback logic
                sh '''
                    echo "Attempting to restore previous state..."
                    # You could implement rollback logic here
                    # For now, just try to restart with the current configuration
                    docker compose down || true
                    docker compose up -d || true
                '''
            }
        }
        
        unstable {
            echo 'Deployment completed with warnings'
        }
    }
}