/**
 * TPLearn Dashboard JavaScript
 * Handles dynamic loading of data from APIs into dashboard elements
 */

class DashboardAPI {
    constructor() {
        // Determine the correct API path based on current location
        const path = window.location.pathname;
        if (path.includes('/dashboards/')) {
            this.baseURL = '../../api/';
        } else {
            this.baseURL = 'api/';
        }
        this.init();
    }

    init() {
        // Load data when page loads
        document.addEventListener('DOMContentLoaded', () => {
            this.loadDashboardData();
        });
    }

    async fetchAPI(endpoint, options = {}) {
        try {
            const response = await fetch(this.baseURL + endpoint, {
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, error: error.message };
        }
    }

    loadDashboardData() {
        const path = window.location.pathname;
        
        if (path.includes('/admin/')) {
            this.loadAdminDashboard();
        } else if (path.includes('/student/')) {
            this.loadStudentDashboard();
        } else if (path.includes('/tutor/')) {
            this.loadTutorDashboard();
        }
    }

    async loadAdminDashboard() {
        console.log('Loading admin dashboard data...');
        
        // Load statistics
        await this.loadAdminStats();
        
        // Load programs table
        await this.loadProgramsTable();
        
        // Load recent activity
        await this.loadRecentActivity();
    }

    async loadAdminStats() {
        const data = await this.fetchAPI('users.php?action=dashboard_stats');
        
        if (data.success) {
            const stats = data.data;
            
            // Update stat cards
            this.updateElement('total-students', stats.total_students || 0);
            this.updateElement('total-tutors', stats.total_tutors || 0);
            this.updateElement('active-programs', stats.active_programs || 0);
            this.updateElement('pending-payments', stats.pending_payments || 0);
        }
    }

    async loadProgramsTable() {
        const data = await this.fetchAPI('programs.php');
        
        if (data.success && data.data.length > 0) {
            const tableBody = document.getElementById('programs-table');
            if (tableBody) {
                // Remove "no programs" row
                const noDataRow = document.getElementById('no-programs-row');
                if (noDataRow) noDataRow.remove();
                
                // Add program rows
                data.data.forEach(program => {
                    const row = this.createProgramTableRow(program);
                    tableBody.appendChild(row);
                });
            }
        }
    }

    createProgramTableRow(program) {
        const row = document.createElement('tr');
        const enrollmentRate = program.capacity > 0 ? (program.enrolled / program.capacity * 100) : 0;
        
        let statusClass = 'bg-green-100 text-green-800';
        let statusText = `${program.capacity - program.enrolled} slots left`;
        
        if (enrollmentRate >= 100) {
            statusClass = 'bg-red-100 text-red-800';
            statusText = 'Full';
        } else if (enrollmentRate >= 80) {
            statusClass = 'bg-yellow-100 text-yellow-800';
            statusText = 'Almost Full';
        }
        
        row.innerHTML = `
            <td class="py-3">${program.name}</td>
            <td class="text-center py-3">${program.enrolled || 0}</td>
            <td class="text-center py-3">₱${program.fee || 0}</td>
            <td class="text-center py-3">
                <span class="px-2 py-1 ${statusClass} text-xs rounded-full">${statusText}</span>
            </td>
        `;
        
        return row;
    }

    async loadRecentActivity() {
        // Load activity logs from the system
        const data = await this.fetchAPI('reports.php?action=recent_activity');
        
        if (data.success && data.data.length > 0) {
            const container = document.getElementById('recent-activity');
            if (container) {
                container.innerHTML = '';
                
                data.data.forEach(activity => {
                    const activityElement = this.createActivityElement(activity);
                    container.appendChild(activityElement);
                });
            }
        }
    }

    createActivityElement(activity) {
        const div = document.createElement('div');
        div.className = 'flex items-center space-x-3 pb-3 border-b';
        
        const colorMap = {
            enrollment: 'bg-green-500',
            payment: 'bg-blue-500',
            program: 'bg-yellow-500',
            user: 'bg-purple-500',
            default: 'bg-gray-500'
        };
        
        const color = colorMap[activity.type] || colorMap.default;
        
        div.innerHTML = `
            <div class="w-2 h-2 ${color} rounded-full"></div>
            <div class="flex-1">
                <p class="text-sm text-gray-800">${activity.message}</p>
                <p class="text-xs text-gray-500">${this.formatTime(activity.created_at)}</p>
            </div>
        `;
        
        return div;
    }

    async loadStudentDashboard() {
        console.log('Loading student dashboard data...');
        
        // Load student statistics
        await this.loadStudentStats();
        
        // Load enrolled programs
        await this.loadStudentPrograms();
        
        // Load student activities
        await this.loadStudentActivities();
    }

    async loadStudentStats() {
        const data = await this.fetchAPI('enrollments.php?action=student_stats');
        
        if (data.success) {
            const stats = data.data;
            
            this.updateElement('enrolled-programs', stats.enrolled_programs || 0);
            this.updateElement('sessions-today', stats.sessions_today || 0);
            this.updateElement('unread-messages', stats.unread_messages || 0);
            this.updateElement('overall-progress', `${stats.overall_progress || 0}%`);
            
            // Update progress status
            const progress = stats.overall_progress || 0;
            let status = 'Getting Started';
            if (progress >= 80) status = 'Excellent';
            else if (progress >= 60) status = 'Good';
            else if (progress >= 40) status = 'Fair';
            
            this.updateElement('progress-status', status);
        }
    }

    async loadStudentPrograms() {
        const data = await this.fetchAPI('enrollments.php?action=my_enrollments');
        
        if (data.success && data.data.length > 0) {
            const container = document.getElementById('programs-container');
            if (container) {
                container.innerHTML = '';
                
                data.data.forEach(enrollment => {
                    const programElement = this.createStudentProgramElement(enrollment);
                    container.appendChild(programElement);
                });
            }
        }
    }

    createStudentProgramElement(enrollment) {
        const div = document.createElement('div');
        div.className = 'program-card p-4';
        
        div.innerHTML = `
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">${enrollment.program_name}</h4>
                    <p class="text-sm text-gray-600">Tutor: ${enrollment.tutor_name || 'Not assigned'}</p>
                </div>
                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                    ${enrollment.status}
                </span>
            </div>
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-600">Progress</span>
                <span class="text-sm font-medium text-gray-900">${enrollment.progress || 0}%</span>
            </div>
            <div class="progress-bar mb-3">
                <div class="progress-fill" style="width: ${enrollment.progress || 0}%"></div>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Next Session:</span>
                <span class="text-sm font-medium text-tplearn-green">${enrollment.next_session || 'TBA'}</span>
            </div>
        `;
        
        return div;
    }

    async loadStudentActivities() {
        // Similar to admin but filtered for current student
        const data = await this.fetchAPI('reports.php?action=my_activity');
        
        if (data.success && data.data.length > 0) {
            const container = document.getElementById('activities-container');
            if (container) {
                container.innerHTML = '';
                
                data.data.forEach(activity => {
                    const activityElement = this.createActivityElement(activity);
                    container.appendChild(activityElement);
                });
            }
        }
    }

    async loadTutorDashboard() {
        console.log('Loading tutor dashboard data...');
        
        // Load tutor statistics
        await this.loadTutorStats();
        
        // Load assigned programs
        await this.loadTutorPrograms();
        
        // Load tutor activities
        await this.loadTutorActivities();
        
        // Load notification counts
        await this.loadNotificationCounts();
    }

    async loadTutorStats() {
        const data = await this.fetchAPI('users.php?action=tutor_stats');
        
        if (data.success) {
            const stats = data.data;
            
            this.updateElement('total-students', stats.total_students || 0);
            this.updateElement('active-programs', stats.active_programs || 0);
            this.updateElement('total-classes', stats.total_classes || 0);
            this.updateElement('average-rating', stats.average_rating || 0);
            
            // Update status indicators
            this.updateElement('student-growth', stats.student_growth || '-');
            this.updateElement('program-status', stats.program_status || '-');
            this.updateElement('weekly-classes', stats.weekly_classes || '-');
            this.updateElement('rating-status', stats.rating_status || '-');
        }
    }

    async loadTutorPrograms() {
        const data = await this.fetchAPI('programs.php?action=my_programs');
        
        if (data.success && data.data.length > 0) {
            const container = document.getElementById('programs-container');
            if (container) {
                container.innerHTML = '';
                
                data.data.forEach(program => {
                    const programElement = this.createTutorProgramElement(program);
                    container.appendChild(programElement);
                });
            }
        }
    }

    createTutorProgramElement(program) {
        const div = document.createElement('div');
        div.className = 'program-card p-4';
        
        const enrollmentPercentage = program.capacity > 0 ? (program.enrolled / program.capacity * 100) : 0;
        
        div.innerHTML = `
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-1">${program.name}</h4>
                    <p class="text-sm text-gray-600">${program.schedule || 'Schedule TBA'}</p>
                </div>
                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                    ${program.status}
                </span>
            </div>
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-600">Enrollment</span>
                <span class="text-sm font-medium text-gray-900">
                    ${program.enrolled || 0}/${program.capacity || 0} students
                </span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${enrollmentPercentage}%"></div>
            </div>
        `;
        
        return div;
    }

    async loadTutorActivities() {
        const data = await this.fetchAPI('reports.php?action=tutor_activity');
        
        if (data.success && data.data.length > 0) {
            const container = document.getElementById('activities-container');
            if (container) {
                container.innerHTML = '';
                
                data.data.forEach(activity => {
                    const activityElement = this.createActivityElement(activity);
                    container.appendChild(activityElement);
                });
            }
        }
    }

    // Helper methods
    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    formatTime(timestamp) {
        try {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffDays > 0) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            if (diffHours > 0) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            if (diffMinutes > 0) return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
            
            return 'Just now';
        } catch (error) {
            return timestamp;
        }
    }

    // Notification and message count updates
    async updateNotificationCounts() {
        const data = await this.fetchAPI('users.php?action=notification_counts');
        
        if (data.success) {
            const counts = data.data;
            
            // Update notification count
            const notificationBadge = document.getElementById('notification-count');
            if (notificationBadge) {
                if (counts.notifications > 0) {
                    notificationBadge.textContent = counts.notifications;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }
            }
            
            // Update message count
            const messageBadge = document.getElementById('message-count');
            if (messageBadge) {
                if (counts.messages > 0) {
                    messageBadge.textContent = counts.messages;
                    messageBadge.classList.remove('hidden');
                } else {
                    messageBadge.classList.add('hidden');
                }
            }
        }
    }
}

// Initialize dashboard when script loads
const dashboard = new DashboardAPI();

// Also update notification counts periodically (every 2 minutes)
setInterval(() => {
    dashboard.loadNotificationCounts();
}, 120000);
setInterval(() => {
    dashboard.updateNotificationCounts();
}, 30000); // Every 30 seconds