<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TPLearn — Transform Learning</title>
  <link rel="icon" href="assets/logo.png" />
  <link rel="stylesheet" href="assets/tailwind.min.css">
  <script>
    // Enable Tailwind plugins if needed
    tailwind.config = {
      theme: {
        extend: {
          animation: {
            fadeUp: "fadeUp 0.8s ease-out",
          },
          keyframes: {
            fadeUp: {
              "0%": {
                opacity: 0,
                transform: "translateY(20px)"
              },
              "100%": {
                opacity: 1,
                transform: "translateY(0)"
              },
            },
          },
        },
      },
    };
  </script>
</head>

<body class="text-gray-800 bg-white">

  <!-- Navbar -->
  <header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
      <div class="flex items-center gap-3">
        <img src="assets/logo.png" alt="TPLearn Logo" class="h-10 w-10 rounded" />

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
  </header>

  <!-- Hero -->
  <section id="home" class="bg-gradient-to-r from-green-400 to-green-300 text-white py-24">
    <div class="container mx-auto text-center px-6 animate-fadeUp">
      <p class="uppercase tracking-wider mb-3 text-sm">🎓 Advanced Online Tutoring Platform</p>
      <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-6">
        Transform Learning with <span class="opacity-80">Live Video Conferencing</span>
      </h1>
      <p class="max-w-2xl mx-auto mb-8 text-lg">
        Complete academic management system featuring HD video conferencing, real-time collaboration tools, automated scheduling, and comprehensive learning analytics for modern education.
      </p>
      <div class="flex justify-center gap-4 mt-8">
        <a href="register.php" class="bg-white text-green-500 font-semibold px-8 py-4 rounded-full shadow-lg hover:bg-gray-100 transition transform hover:scale-105">
          🚀 Start Free Trial
        </a>
        <a href="#demo" class="bg-transparent border-2 border-white text-white font-semibold px-8 py-4 rounded-full hover:bg-white hover:text-green-500 transition">
          📹 Watch Demo
        </a>
      </div>
      <div class="mt-12 text-sm opacity-90">
        ✅ No Downloads Required • ✅ Works on All Devices • ✅ HTTPS Secure
      </div>
    </div>
  </section>

    <!-- Portals -->
  <section id="portals" class="py-20 bg-gray-50">
    <div class="container mx-auto px-6 text-center mb-12 animate-fadeUp">
      <h2 class="text-3xl font-bold mb-4">🎯 Three Specialized Portals</h2>
      <p class="text-lg text-gray-600 max-w-2xl mx-auto">Role-based access ensures everyone gets the right tools. From admin oversight to live tutoring sessions, everything is optimized for your workflow.</p>
    </div>
    <div class="container mx-auto px-6 grid gap-8 md:grid-cols-3">
      <!-- Admin -->
      <div class="bg-white p-8 rounded-lg shadow-lg text-left transform hover:scale-105 transition duration-300 border-l-4 border-blue-500">
        <div class="flex items-center mb-4">
          <span class="text-3xl mr-3">👨‍💼</span>
          <h3 class="text-xl font-bold">Admin Portal</h3>
        </div>
        <ul class="text-gray-600 space-y-2 text-sm mb-6">
          <li>� <strong>Real-time Analytics</strong> - Student performance & engagement metrics</li>
          <li>� <strong>User Management</strong> - Students, tutors, and program administration</li>
          <li>💰 <strong>Payment Tracking</strong> - Automated billing and financial reports</li>
          <li>� <strong>System Scheduling</strong> - Master calendar and resource allocation</li>
          <li>🔧 <strong>Platform Settings</strong> - Video quality, system configuration</li>
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
          <li>� <strong>Interactive Sessions</strong> - Engaging learning with personalized instruction</li>
          <li>📝 <strong>Interactive Whiteboard</strong> - Real-time collaboration tools</li>
          <li>📚 <strong>Content Management</strong> - Upload materials, create assignments</li>
          <li>📈 <strong>Progress Tracking</strong> - Student analytics and performance reports</li>
          <li>⏰ <strong>Session Management</strong> - Calendar, attendance, and recordings</li>
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
          <li>�️ <strong>Join Live Sessions</strong> - One-click video conferencing access</li>
          <li>� <strong>Mobile Ready</strong> - Learn from any device, anywhere</li>
          <li>� <strong>Assignment Hub</strong> - Submit work, view grades & feedback</li>
          <li>� <strong>Easy Payments</strong> - Secure online payment processing</li>
          <li>� <strong>Progress Dashboard</strong> - Track learning goals and achievements</li>
        </ul>
        <a href="login.php?role=student" class="block text-center bg-purple-500 text-white py-3 rounded-lg hover:bg-purple-600 transition font-semibold">
          Student Learning Hub
        </a>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="features" class="py-20 bg-white animate-fadeUp">
    <div class="container mx-auto px-6 text-center mb-12">
      <h2 class="text-3xl font-bold mb-4">🚀 Advanced Learning Technology</h2>
      <p class="text-lg text-gray-600 max-w-2xl mx-auto">Built-in video conferencing, smart scheduling, and comprehensive learning tools designed for modern education needs.</p>
    </div>
    <div class="container mx-auto px-6 grid gap-8 md:grid-cols-3">
      <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-blue-200">
        <div class="text-4xl mb-4">🎥</div>
        <h3 class="font-bold text-lg mb-3">Interactive Learning Tools</h3>
        <p class="text-sm text-gray-700 mb-4">Crystal-clear HD video calls with screen sharing, interactive whiteboard, and real-time chat. No downloads required - works directly in the browser.</p>
        <div class="text-xs text-blue-600 font-semibold">✅ Recently Enhanced & Fixed</div>
      </div>
      
      <div class="bg-gradient-to-br from-green-50 to-green-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-green-200">
        <div class="text-4xl mb-4">📅</div>
        <h3 class="font-bold text-lg mb-3">AI-Powered Scheduling</h3>
        <p class="text-sm text-gray-700 mb-4">Smart calendar system with automatic conflict detection, time zone handling, and automated reminders via email and SMS.</p>
        <div class="text-xs text-green-600 font-semibold">✅ Auto-conflict Resolution</div>
      </div>
      
      <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-purple-200">
        <div class="text-4xl mb-4">📚</div>
        <h3 class="font-bold text-lg mb-3">Integrated Learning Management</h3>
        <p class="text-sm text-gray-700 mb-4">Complete LMS with assignment creation, automated grading, progress tracking, and detailed analytics dashboards.</p>
        <div class="text-xs text-purple-600 font-semibold">✅ Real-time Analytics</div>
      </div>
      
      <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-yellow-200">
        <div class="text-4xl mb-4">💳</div>
        <h3 class="font-bold text-lg mb-3">Automated Payment Processing</h3>
        <p class="text-sm text-gray-700 mb-4">Secure payment gateway integration with automated billing, receipt generation, and financial reporting for seamless transactions.</p>
        <div class="text-xs text-yellow-600 font-semibold">✅ PCI Compliant</div>
      </div>
      
      <div class="bg-gradient-to-br from-red-50 to-red-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-red-200">
        <div class="text-4xl mb-4">🔔</div>
        <h3 class="font-bold text-lg mb-3">Smart Notification System</h3>
        <p class="text-sm text-gray-700 mb-4">Multi-channel alerts for sessions, assignments, payments, and updates via email, SMS, and in-app notifications.</p>
        <div class="text-xs text-red-600 font-semibold">✅ Multi-channel Delivery</div>
      </div>
      
      <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-8 rounded-xl shadow-lg hover:shadow-xl transition border border-indigo-200">
        <div class="text-4xl mb-4">🔐</div>
        <h3 class="font-bold text-lg mb-3">Enterprise Security</h3>
        <p class="text-sm text-gray-700 mb-4">Role-based access control, HTTPS encryption, secure video conferencing, and comprehensive audit trails for complete data protection.</p>
        <div class="text-xs text-indigo-600 font-semibold">✅ Bank-level Security</div>
      </div>
    </div>
    
    <!-- Technical Specs -->
    <div class="container mx-auto px-6 mt-16">
      <div class="bg-gray-50 rounded-xl p-8 text-center">
        <h3 class="text-xl font-bold mb-6">🔧 Technical Specifications</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
          <div>
            <div class="font-semibold text-blue-600">Video Quality</div>
            <div class="text-gray-700">Up to 1080p HD</div>
          </div>
          <div>
            <div class="font-semibold text-green-600">Browser Support</div>
            <div class="text-gray-700">Chrome, Firefox, Safari, Edge</div>
          </div>
          <div>
            <div class="font-semibold text-purple-600">Platform</div>
            <div class="text-gray-700">Windows, Mac, iOS, Android</div>
          </div>
          <div>
            <div class="font-semibold text-orange-600">Uptime</div>
            <div class="text-gray-700">99.9% Guaranteed</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Demo Section -->
  <section id="demo" class="py-20 bg-gradient-to-r from-gray-900 to-gray-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h2 class="text-3xl font-bold mb-6">🎬 See TPLearn in Action</h2>
      <p class="text-lg text-gray-300 max-w-2xl mx-auto mb-8">
        Experience the power of our video conferencing and learning management system. Watch how tutors and students connect seamlessly.
      </p>
      <div class="grid gap-8 md:grid-cols-3 mb-12">
        <div class="bg-gray-800 p-6 rounded-lg">
          <div class="text-4xl mb-4">🎥</div>
          <h3 class="font-bold mb-2">Live Video Sessions</h3>
          <p class="text-sm text-gray-300">HD video conferencing with screen sharing and interactive whiteboard tools.</p>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg">
          <div class="text-4xl mb-4">📱</div>
          <h3 class="font-bold mb-2">Mobile Ready</h3>
          <p class="text-sm text-gray-300">Access from any device - desktop, tablet, or smartphone with full functionality.</p>
        </div>
        <div class="bg-gray-800 p-6 rounded-lg">
          <div class="text-4xl mb-4">⚡</div>
          <h3 class="font-bold mb-2">Instant Setup</h3>
          <p class="text-sm text-gray-300">One-click session joining with no downloads or installations required.</p>
        </div>
      </div>
      <div class="flex justify-center gap-4">
        <a href="video-conference-test-fixed.html" target="_blank" class="bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 transition font-semibold">
          🔧 Test Video Conferencing
        </a>
        <a href="register.php" class="bg-green-600 text-white px-8 py-4 rounded-lg hover:bg-green-700 transition font-semibold">
          🚀 Start Free Trial
        </a>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="py-20 bg-gray-50">
    <div class="container mx-auto px-6 max-w-4xl text-center bg-white p-12 rounded-xl shadow-xl animate-fadeUp">
      <h2 class="text-3xl font-bold mb-6">🎯 Ready to Transform Your Educational Experience?</h2>
      <p class="text-lg text-gray-600 mb-8">
        Join thousands of educators and students who trust TPLearn for seamless online learning. 
        Start your free trial today and discover the difference quality technology makes.
      </p>
      
      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-10 text-center">
        <div>
          <div class="text-2xl font-bold text-green-600">99.9%</div>
          <div class="text-sm text-gray-600">Uptime</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-blue-600">1080p</div>
          <div class="text-sm text-gray-600">HD Video</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-purple-600">24/7</div>
          <div class="text-sm text-gray-600">Support</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-orange-600">5★</div>
          <div class="text-sm text-gray-600">Rated</div>
        </div>
      </div>
      
      <div class="flex flex-col sm:flex-row justify-center gap-4">
        <a href="register.php" class="px-8 py-4 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold text-lg">
          🎓 Start Free 30-Day Trial
        </a>
        <a href="login.php" class="px-8 py-4 border-2 border-green-500 text-green-500 rounded-lg hover:bg-green-50 transition font-semibold text-lg">
          📞 Schedule a Demo
        </a>
      </div>
      
      <div class="mt-6 text-sm text-gray-500">
        ✅ No credit card required • ✅ Full access to all features • ✅ Cancel anytime
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
        <p class="mb-4">Transforming education through innovative video conferencing and comprehensive learning management technology.</p>
        <div class="text-xs text-gray-500">
          <p>🎥 HD Video Conferencing</p>
          <p>📱 Mobile & Desktop Ready</p>
          <p>🔒 Enterprise Security</p>
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
          <li><a href="video-conference-test-fixed.html" class="hover:text-green-600">🔧 Test Video Conference</a></li>
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