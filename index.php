<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TPLearn — Transform Learning</title>
  <!-- Favicon and Icons -->
  <link rel="icon" type="image/png" sizes="32x32" href="assets/logonew.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="assets/logonew.png" />
  <link rel="shortcut icon" href="assets/logonew.png" />
  <link rel="apple-touch-icon" href="assets/logonew.png" />
  <meta name="msapplication-TileImage" content="assets/logonew.png" />
  <meta name="msapplication-TileColor" content="#10B981" />
  <meta name="theme-color" content="#10B981" />
  <link rel="stylesheet" href="assets/tailwind.min.css">
  <style>
    @keyframes fadeUp {
      0% {
        opacity: 0;
        transform: translateY(20px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .animate-fadeUp {
      animation: fadeUp 0.8s ease-out;
    }
    
    /* Enhanced animations for better UX */
    .animate-fadeUp-delayed {
      animation: fadeUp 0.8s ease-out 0.2s both;
    }
    
    @media (prefers-reduced-motion: reduce) {
      .animate-fadeUp,
      .animate-fadeUp-delayed {
        animation: none;
      }
    }
  </style>
</head>

<body class="text-gray-800 bg-white">

  <!-- Navbar -->
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
      <div class="flex items-center gap-3">
        <img src="assets/logonew.png" alt="TPLearn Logo" class="h-10 w-10 rounded" />

        <div class="leading-tight">
          <h1 class="text-xl font-bold text-green-600">TPLearn</h1>
          <p class="text-xs text-gray-500">Tisa at Pisara Tutoring Center</p>
        </div>
      </div>

      <nav class="hidden md:flex gap-6 text-sm font-medium">
        <a href="#home" class="hover:text-green-500">Home</a>
        <a href="#portals" class="hover:text-green-500">Portals</a>
        <a href="#features" class="hover:text-green-500">Features</a>
        <a href="#about" class="hover:text-green-500">About</a>
      </nav>
      <div class="flex gap-3">
        <a href="login.php" class="px-4 py-2 bg-white text-green-600 border border-green-500 rounded-md text-sm font-semibold hover:bg-green-50 transition">
          Login
        </a>
        <a href="register.php" class="px-4 py-2 bg-green-500 text-white rounded-md text-sm font-semibold hover:bg-green-600 transition">
          Get Started
        </a>

      </div>
    </div>
  </header>

  <!-- Hero -->
  <section id="home" class="bg-gradient-to-r from-green-400 to-green-300 text-white py-24">
    <div class="container mx-auto text-center px-6 animate-fadeUp">
      <p class="uppercase tracking-wider mb-3 text-sm">🎓 Comprehensive Learning Management System</p>
      <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-6">
        TPLearn: <span class="opacity-80">Complete Educational Platform</span>
      </h1>
      <p class="max-w-2xl mx-auto mb-8 text-lg">
        Full-featured LMS with student enrollment, tutor management, payment processing, assessment tools, video conferencing integration, and real-time analytics. Built for Tisa at Pisara Tutoring Center.
      </p>
      <div class="flex justify-center gap-4 mt-8">
        <a href="register.php" class="bg-white text-green-500 font-semibold px-8 py-4 rounded-full shadow-lg hover:bg-gray-100 transition transform hover:scale-105">
          🎓 Create Account
        </a>
        <a href="login.php" class="bg-transparent border-2 border-white text-white font-semibold px-8 py-4 rounded-full hover:bg-white hover:text-green-500 transition">
          🔐 Login to Portal
        </a>
      </div>
      <div class="mt-12 text-sm opacity-90">
        ✅ Student Enrollment • ✅ Payment Processing • ✅ Assessment Tools • ✅ Video Conferencing
      </div>
    </div>
  </section>

    <!-- Portals -->
  <section id="portals" class="py-20 bg-gray-50">
    <div class="container mx-auto px-6 text-center mb-12 animate-fadeUp">
      <h2 class="text-4xl font-bold mb-4">🚪 Choose Your Portal</h2>
      <p class="text-lg text-gray-600 max-w-3xl mx-auto">
        Access your personalized dashboard with role-specific tools and features designed for your educational journey.
      </p>
    </div>

    <div class="container mx-auto px-6 grid gap-8 md:grid-cols-3 max-w-6xl">
      <!-- Admin -->
      <div class="bg-white p-8 rounded-lg shadow-lg text-left transform hover:scale-105 transition duration-300 border-l-4 border-blue-500">
        <div class="flex items-center mb-4">
          <span class="text-3xl mr-3">👨‍💼</span>
          <h3 class="text-xl font-bold">Admin Dashboard</h3>
        </div>
        <ul class="text-gray-600 space-y-2 text-sm mb-6">
          <li>👥 <strong>User Management</strong> - Complete control over students, tutors, and programs</li>
          <li>💰 <strong>Payment Analytics</strong> - Track revenue, process payments, and generate reports</li>
          <li>📊 <strong>System Reports</strong> - Enrollment analytics, attendance tracking, and performance metrics</li>
          <li>⚙️ <strong>System Configuration</strong> - Program management, pricing, and platform settings</li>
        </ul>
        <a href="login.php?role=admin" class="block text-center bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition font-semibold">
          Access Admin Dashboard
        </a>
      </div>

      <!-- Tutor -->
      <div class="bg-white p-8 rounded-lg shadow-lg text-left transform hover:scale-105 transition duration-300 border-l-4 border-green-500">
        <div class="flex items-center mb-4">
          <span class="text-3xl mr-3">👩‍🏫</span>
          <h3 class="text-xl font-bold">Tutor Portal</h3>
        </div>
        <ul class="text-gray-600 space-y-2 text-sm mb-6">
          <li>📚 <strong>Program Management</strong> - Create and manage tutoring programs with materials</li>
          <li>👨‍🎓 <strong>Student Oversight</strong> - Track attendance, grades, and student progress</li>
          <li>📝 <strong>Assessment Tools</strong> - Create assignments, quizzes, and grade submissions</li>
          <li>🎥 <strong>Video Sessions</strong> - Integrated Jitsi Meet for live tutoring sessions</li>
          <li>📊 <strong>Performance Analytics</strong> - Detailed reports on student engagement and outcomes</li>
        </ul>
        <a href="login.php?role=tutor" class="block text-center bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 transition font-semibold">
          Enter Tutor Workspace
        </a>
      </div>

      <!-- Student -->
      <div class="bg-white p-8 rounded-lg shadow-lg text-left transform hover:scale-105 transition duration-300 border-l-4 border-purple-500">
        <div class="flex items-center mb-4">
          <span class="text-3xl mr-3">🎓</span>
          <h3 class="text-xl font-bold">Student Portal</h3>
        </div>
        <ul class="text-gray-600 space-y-2 text-sm mb-6">
          <li>� <strong>Program Enrollment</strong> - Browse and enroll in available tutoring programs</li>
          <li>� <strong>Secure Payments</strong> - Process payments with receipt generation and history</li>
          <li>� <strong>Assignment Submission</strong> - Upload assignments and receive graded feedback</li>
          <li>🎥 <strong>Live Sessions</strong> - Join video conferences with tutors using integrated Jitsi Meet</li>
          <li>📊 <strong>Academic Progress</strong> - View grades, attendance records, and performance analytics</li>
        </ul>
        <a href="login.php?role=student" class="block text-center bg-purple-500 text-white py-3 rounded-lg hover:bg-purple-600 transition font-semibold">
          Student Learning Hub
        </a>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="features" class="py-20 bg-white">
    <div class="container mx-auto px-6 text-center mb-16 animate-fadeUp">
      <h2 class="text-4xl font-bold mb-6">🚀 Core Platform Features</h2>
      <p class="text-lg text-gray-600 max-w-3xl mx-auto">
        Everything you need for comprehensive tutoring center management - from enrollment to graduation.
      </p>
    </div>

    <div class="container mx-auto px-6 grid gap-8 md:grid-cols-2 lg:grid-cols-3 max-w-6xl">
      <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-blue-200">
        <div class="text-4xl mb-4">👥</div>
        <h3 class="font-bold text-lg mb-3">Student Enrollment System</h3>
        <p class="text-sm text-gray-700 mb-4">Complete enrollment workflow with program selection, payment processing, email verification, and automated confirmation system.</p>
        <div class="text-xs text-blue-600 font-semibold">✅ Email Verification & Notifications</div>
      </div>
      
      <div class="bg-gradient-to-br from-green-50 to-green-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-green-200">
        <div class="text-4xl mb-4">�</div>
        <h3 class="font-bold text-lg mb-3">Payment Processing</h3>
        <p class="text-sm text-gray-700 mb-4">Secure payment gateway with receipt uploads, payment verification, automated notifications, and comprehensive payment history tracking.</p>
        <div class="text-xs text-green-600 font-semibold">✅ Receipt Management & History</div>
      </div>

      <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-purple-200">
        <div class="text-4xl mb-4">�</div>
        <h3 class="font-bold text-lg mb-3">Assessment & Grading</h3>
        <p class="text-sm text-gray-700 mb-4">Create assignments, manage submissions, provide detailed feedback, and track student progress with comprehensive grading tools.</p>
        <div class="text-xs text-purple-600 font-semibold">✅ Assignment Submission & Grading</div>
      </div>

      <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-orange-200">
        <div class="text-4xl mb-4">🎥</div>
        <h3 class="font-bold text-lg mb-3">Video Conferencing Integration</h3>
        <p class="text-sm text-gray-700 mb-4">Integrated Jitsi Meet for seamless video tutoring sessions with session management, attendance tracking, and recording capabilities.</p>
        <div class="text-xs text-orange-600 font-semibold">✅ Jitsi Meet Integration</div>
      </div>

      <div class="bg-gradient-to-br from-teal-50 to-teal-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-teal-200">
        <div class="text-4xl mb-4">�</div>
        <h3 class="font-bold text-lg mb-3">Analytics & Reporting</h3>
        <p class="text-sm text-gray-700 mb-4">Comprehensive dashboard with enrollment analytics, payment reports, student progress tracking, and tutor performance metrics.</p>
        <div class="text-xs text-teal-600 font-semibold">✅ Real-time Analytics Dashboard</div>
      </div>

      <div class="bg-gradient-to-br from-red-50 to-red-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-red-200">
        <div class="text-4xl mb-4">�</div>
        <h3 class="font-bold text-lg mb-3">Responsive Design</h3>
        <p class="text-sm text-gray-700 mb-4">Fully responsive interface that works perfectly on desktop, tablet, and mobile devices with optimized user experience for all screen sizes.</p>
        <div class="text-xs text-red-600 font-semibold">✅ Mobile-first Design</div>
      </div>
    </div>
  </section>

  <!-- Demo/Test Section -->
  <section id="demo" class="py-20 bg-gradient-to-r from-gray-800 to-gray-900 text-white">
    <div class="container mx-auto px-6 text-center animate-fadeUp">
      <h2 class="text-3xl font-bold mb-6">🎯 Explore TPLearn Features</h2>
      <p class="text-lg text-gray-300 max-w-3xl mx-auto mb-12">
        Experience our comprehensive learning management system with real user accounts and full functionality testing.
      </p>
      
      <div class="grid md:grid-cols-3 gap-8 max-w-4xl mx-auto mb-12">
        <div class="text-center">
          <div class="text-4xl mb-4">📚</div>
          <h3 class="font-bold mb-2">Full LMS Features</h3>
          <p class="text-sm text-gray-300">Complete enrollment, payment processing, assignments, and grading system.</p>
        </div>
        <div class="text-center">
          <div class="text-4xl mb-4">🎥</div>
          <h3 class="font-bold mb-2">Video Integration</h3>
          <p class="text-sm text-gray-300">Integrated Jitsi Meet for seamless video tutoring sessions.</p>
        </div>
        <div class="text-center">
          <div class="text-4xl mb-4">📊</div>
          <h3 class="font-bold mb-2">Real-time Analytics</h3>
          <p class="text-sm text-gray-300">Comprehensive dashboard with enrollment and performance tracking.</p>
        </div>
      </div>
      <div class="flex justify-center gap-4">
        <a href="login.php" class="bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition font-semibold">
          🔐 Login to Demo
        </a>
        <a href="register.php" class="bg-green-600 text-white px-8 py-4 rounded-lg hover:bg-green-700 transition font-semibold">
          🎓 Create Account
        </a>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6 max-w-4xl text-center bg-white p-12 rounded-xl shadow-xl animate-fadeUp">
      <h2 class="text-3xl font-bold mb-6">🚀 Ready to Get Started with TPLearn?</h2>
      <p class="text-lg text-gray-600 mb-8">
        Join Tisa at Pisara Tutoring Center's comprehensive learning management system. 
        Experience streamlined enrollment, integrated payments, and powerful educational tools.
      </p>
      
      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-10 text-center">
        <div>
          <div class="text-2xl font-bold text-green-600">MySQL</div>
          <div class="text-sm text-gray-600">Database</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-blue-600">PHP 8+</div>
          <div class="text-sm text-gray-600">Backend</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-purple-600">Jitsi</div>
          <div class="text-sm text-gray-600">Video Calls</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-orange-600">100%</div>
          <div class="text-sm text-gray-600">Open Source</div>
        </div>
      </div>
      
      <div class="flex flex-col sm:flex-row justify-center gap-4">
        <a href="register.php" class="px-8 py-4 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold text-lg">
          🎓 Register as Student
        </a>
        <a href="login.php" class="px-8 py-4 border-2 border-green-500 text-green-500 rounded-lg hover:bg-green-50 transition font-semibold text-lg">
          � Access Your Portal
        </a>
      </div>
      
      <div class="mt-6 text-sm text-gray-500">
        ✅ Full feature access • ✅ Secure payment processing • ✅ Email verification included
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-white py-16 border-t">
    <div class="container mx-auto px-6 grid gap-8 md:grid-cols-4 text-sm text-gray-600">
      <div>
        <div class="flex items-center gap-3 mb-4">
          <img src="assets/logo.png" alt="TPLearn Logo" class="h-8 w-8 rounded" />
          <h4 class="font-bold text-lg text-green-600">TPLearn</h4>
        </div>
        <p class="mb-4">Comprehensive Learning Management System for Tisa at Pisara Tutoring Center with integrated enrollment, payment processing, and educational tools.</p>
        <div class="text-xs text-gray-500">
          <p>🎓 Student Enrollment System</p>
          <p>💳 Integrated Payment Processing</p>
          <p>🎥 Video Conferencing (Jitsi Meet)</p>
          <p>📊 Real-time Analytics Dashboard</p>
        </div>
      </div>
      <div>
        <h4 class="font-semibold mb-4 text-gray-800">Platform Access</h4>
        <ul class="space-y-2">
          <li><a href="login.php?role=admin" class="hover:text-green-600">👨‍💼 Admin Portal</a></li>
          <li><a href="login.php?role=tutor" class="hover:text-green-600">👩‍🏫 Tutor Portal</a></li>
          <li><a href="login.php?role=student" class="hover:text-green-600">🎓 Student Portal</a></li>
          <li><a href="register.php" class="hover:text-green-600">📝 Register</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold mb-4 text-gray-800">Resources & Support</h4>
        <ul class="space-y-2">
          <li><a href="#" class="hover:text-green-600">🔧 Test Video Conference</a></li>
          <li><a href="#" class="hover:text-green-600">📚 Help Center</a></li>
          <li><a href="#" class="hover:text-green-600">🔌 API Documentation</a></li>
          <li><a href="#" class="hover:text-green-600">📊 System Status</a></li>
          <li><a href="#" class="hover:text-green-600">💬 Contact Support</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold mb-4 text-gray-800">Contact Information</h4>
        <div class="space-y-2">
          <p class="flex items-center"><span class="mr-2">🏢</span>Tisa at Pisara Tutoring Center</p>
          <p class="flex items-center"><span class="mr-2">📍</span>Learning Innovation District</p>
          <p class="flex items-center"><span class="mr-2">📞</span>+1 (555) 123‑4567</p>
          <p class="flex items-center"><span class="mr-2">✉️</span>support@tplearn.edu</p>
          <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="text-xs font-semibold text-green-600">🌐 Available 24/7</p>
            <p class="text-xs">Multi-language support</p>
          </div>
        </div>
      </div>
    </div>
    <div class="border-t border-gray-200 mt-12 pt-8 text-center">
      <div class="text-gray-500 text-xs mb-4">
        <span class="mx-2">🔒 HTTPS Secure</span>
        <span class="mx-2">•</span>
        <span class="mx-2">🛡️ Privacy Protected</span>
        <span class="mx-2">•</span>
        <span class="mx-2">⚡ 99.9% Uptime</span>
      </div>
      <div class="text-gray-500 text-xs">
        © 2025 TPLearn Platform. All rights reserved. | Built with ❤️ for modern education
      </div>
    </div>
  </footer>

</body>

</html>