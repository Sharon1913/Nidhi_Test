pipeline {
    agent any

    triggers {
        pollSCM('H/5 * * * *') // every 5 min check
    }

    stage('Initialize'){
        def dockerHome = tool 'myDocker'
        env.PATH = "${dockerHome}/bin:${env.PATH}"
    }

    stages {
        stage('Checkout') {
            steps {
                echo 'Fetching latest code from GitHub...'
                git branch: 'main', url: 'https://github.com/Sharon1913/Nidhi_Test.git'
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
    }

    post {
        success {
            echo '✅ Deployment complete!'
        }
        failure {
            echo '❌ Deployment failed. Attempting recovery...'
            sh 'docker-compose up -d || true'
        }
    }
}
