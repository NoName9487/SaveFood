<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePlate - MainPage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2ecc71;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .logo i {
            color: white;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 25px;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: var(--radius);
        }
        
        nav a:hover, nav a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .auth-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .user-info span {
            font-weight: 600;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #27ae60;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background-color: white;
            color: var(--primary);
        }
        
        .btn-danger {
            background-color: var(--accent);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto 30px;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }

        .hero {
            height: 85vh;
            background-size: cover;
            background-position: center;
            transition: background-image 0.5s ease-in-out;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Dark overlay for better text visibility */
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        
        .hero > .container {
            position: relative;
            z-index: 2;
        }
        
        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(255, 255, 255, 0.3);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        .carousel-control:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .carousel-control.prev {
            left: 20px;
        }
        
        .carousel-control.next {
            right: 20px;
        }
        
        .carousel-control i {
            color: white;
            font-size: 24px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        
        .carousel-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }
        
        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .indicator.active {
            background-color: white;
            transform: scale(1.2);
        }
        
        .features {
            padding: 80px 0;
            background-color: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 50px;
            font-size: 2.2rem;
            color: var(--dark);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--light);
            border-radius: var(--radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .feature-card h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        /* Why Choose Us Section */
        .why-choose-us {
            padding: 80px 0;
            background-color: white;
        }
        
        .benefits-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .benefit-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }
        
        .benefit-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow);
        }
        
        .benefit-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }
        
        .benefit-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .benefit-card p {
            color: var(--gray);
            line-height: 1.6;
        }
        
        /* About Section Styles */
        .about {
            padding: 80px 0;
            background-color: #f5f5f5;
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        
        .about-text h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        .about-text p {
            margin-bottom: 20px;
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        .impact-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--dark);
        }
        
        .about-image {
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .mission-section {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            padding: 60px 0;
            color: white;
            text-align: center;
            margin-top: 60px;
        }
        
        .mission-statement {
            max-width: 800px;
            margin: 0 auto;
            font-size: 1.4rem;
            line-height: 1.8;
            font-style: italic;
        }
        
        .mission-statement i {
            color: rgba(255, 255, 255, 0.7);
            font-size: 2rem;
            display: block;
            margin-bottom: 20px;
        }
        
        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-section {
            flex: 1;
            min-width: 250px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .footer-section a {
            color: white;
            text-decoration: none;
        }
        
        .footer-section a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Login Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: var(--radius);
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .close-modal:hover {
            color: var(--dark);
        }
        
        .modal-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .about-content {
                grid-template-columns: 1fr;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .carousel-control {
                width: 40px;
                height: 40px;
            }
            
            .carousel-control i {
                font-size: 18px;
            }
            
            .impact-stats {
                grid-template-columns: 1fr;
            }
            
            .benefits-container {
                grid-template-columns: 1fr;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <div class="modal-icon">
                <i class="fas fa-user-lock"></i>
            </div>
            <h2>Login Required</h2>
            <p>Please log in to access this feature and start reducing food waste with SavePlate.</p>
            <div class="modal-buttons">
                <button class="btn btn-primary" onclick="window.location.href='/bit216_assignment/login_register.php'">Login</button>
            </div>
        </div>
    </div>
    
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-leaf"></i>
                    <span>SavePlate</span>
                </div>
                
                <nav>
                    <ul>
                        <li><a href="index.php" class="active">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#why-choose-us">Why Choose Us</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
                
                <div class="auth-buttons">
                    <button type="submit" class="btn btn-outline" onclick="window.location.href='/bit216_assignment/login_register.php'"><b>Login</b></button>
                    <button type="submit" class="btn btn-primary" onclick="window.location.href='/bit216_assignment/login_register.php'"><b>Sign Up</b></button>
                </div>
        </div>
    </header>
    
    <section class="hero" id="hero">
        <div class="carousel-control prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="carousel-control next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </div>
        
        <div class="carousel-indicators" id="indicators"></div>
        
        <div class="container">
            <h1>Reduce Food Waste, Save Money</h1>
            <p>SavePlate helps Malaysian households manage their food inventory, reduce waste, and donate surplus food to those in need.</p>
            <a href="#features" class="btn btn-primary">Learn More</a>
        </div>
    </section>
    
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Key Features</h2>
            
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Food Inventory Management</h3>
                    <p>Track your food items with expiry dates, quantities, and storage locations.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Expiry Alerts</h3>
                    <p>Get notifications before your food items expire so you can use them in time.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-hands-helping"></i>
                    <h3>Donation Facilitation</h3>
                    <p>Easily donate surplus food to people in need or local charities.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-utensils"></i>
                    <h3>Meal Planning</h3>
                    <p>Plan meals based on your inventory to reduce waste and save money.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
                
                <div class="feature-card" >
                    <i class="fas fa-chart-pie"></i>
                    <h3>Food Analytics</h3>
                    <p>Track your food-saving progress with visual reports and insights.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Privacy Protection</h3>
                    <p>Your data is secure with robust privacy settings and 2FA options.</p>
                    <button class="btn btn-primary explore-btn" style="margin-top: 15px;"> Explore More </button>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Why Choose Us Section -->
    <!--testing-->
    <section class="why-choose-us" id="why-choose-us">
        <div class="container">
            <h2 class="section-title">Why Choose SavePlate</h2>
            
            <div class="benefits-container">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Reduce Food Waste</h3>
                    <p>Our smart tracking system helps you use food before it expires, significantly reducing household waste.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Save Money</h3>
                    <p>By minimizing food waste and optimizing your grocery shopping, you can save hundreds annually.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Help Your Community</h3>
                    <p>Easily donate surplus food to those in need, making a positive impact in your community.</p>
                </div>
                
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Track Your Progress</h3>
                    <p>Get detailed insights into your consumption patterns and see your environmental impact.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Plan Your Meals Wisely</h3>
                    <p>Plan weekly meals using your current food inventory, helping reduce waste and optimize ingredient usage before expiry.</p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Eat Healthier</h3>
                    <p>With better food tracking, you'll always have fresh ingredients at hand — making it easier to prepare nutritious meals for you and your family.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="about" id="about">
        <div class="container">
            <h2 class="section-title">About SavePlate</h2>
            
            <div class="about-content">
                <div class="about-text">
                    <h2>Reducing Food Waste, Saving Money</h2>
                    <p>SavePlate is an innovative platform dedicated to tackling one of Malaysia's pressing issues - food waste. We provide households with smart tools to manage their food inventory, reduce waste, and ultimately save money while contributing to a more sustainable future.</p>
                    
                    <p>At SavePlate, we believe that reducing food waste shouldn't be complicated. Our mission is to empower Malaysian families with simple yet effective tools that make food management effortless, economical, and environmentally friendly.</p>
                    
                    <div class="impact-stats">
                        <div class="stat-box">
                            <div class="stat-number">40%</div>
                            <div class="stat-label">Average Reduction in Food Waste</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">RM150</div>
                            <div class="stat-label">Monthly Savings per Household</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">50K+</div>
                            <div class="stat-label">Meals Donated to Communities</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">100+</div>
                            <div class="stat-label">Tonnes of Food Saved from Landfills</div>
                        </div>
                    </div>
                </div>
                
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1571902943202-507ec2618e8f?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&h=600&q=80" alt="Food saving illustration">
                </div>
            </div>
            
            <div class="mission-section">
                <div class="mission-statement">
                    <i class="fas fa-quote-left"></i>
                    <p>When you use SavePlate, you're not just saving money - you're joining a community dedicated to creating a sustainable food culture in Malaysia. Together, we can make a significant impact on food waste reduction while keeping more money in your pocket.</p>
                </div>
            </div>
        </div>
    </section>
    
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>SavePlate</h3>
                    <p>Helping Malaysian households reduce food waste through intelligent inventory management and donation facilitation.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <p><a href="#features">Features</a></p>
                    <p><a href="#why-choose-us">Why Choose Us</a></p>
                    <p><a href="#about">About</a></p>
                    <p><a href="#">Privacy Policy</a></p>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> info@saveplate.com</p>
                    <p><i class="fas fa-phone"></i> +60 3 1234 5678</p>
                    <p><i class="fas fa-map-marker-alt"></i> Kuala Lumpur, Malaysia</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 SavePlate. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Image carousel functionality
        const images = [
            "https://images.unsplash.com/photo-1542838132-92c53300491e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80",
            "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80",
            "https://images.unsplash.com/photo-1565958011703-44f9829ba187?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&h=900&q=80" 
        ];

        let currentIndex = 0;
        const heroSection = document.getElementById("hero");
        const indicatorsContainer = document.getElementById("indicators");
        
        // Create indicators
        images.forEach((_, index) => {
            const indicator = document.createElement("div");
            indicator.classList.add("indicator");
            if (index === 0) indicator.classList.add("active");
            indicator.addEventListener("click", () => {
                currentIndex = index;
                updateCarousel();
            });
            indicatorsContainer.appendChild(indicator);
        });
        
        // Function to change slide
        function changeSlide(direction) {
            currentIndex = (currentIndex + direction + images.length) % images.length;
            updateCarousel();
        }
        
        // Update carousel display
        function updateCarousel() {
            heroSection.style.backgroundImage = `url('${images[currentIndex]}')`;
            
            // Update active indicator
            document.querySelectorAll(".indicator").forEach((indicator, index) => {
                if (index === currentIndex) {
                    indicator.classList.add("active");
                } else {
                    indicator.classList.remove("active");
                }
            });
        }
        
        // Auto-advance slides
        let slideInterval = setInterval(() => changeSlide(1), 5000);
        
        // Pause auto-advancement when hovering over carousel
        heroSection.addEventListener("mouseenter", () => {
            clearInterval(slideInterval);
        });
        
        heroSection.addEventListener("mouseleave", () => {
            slideInterval = setInterval(() => changeSlide(1), 5000);
        });
        
        // Initialize carousel
        updateCarousel();
        
        // Feature cards animation
        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Login Modal Functionality
        const modal = document.getElementById("loginModal");
        const closeModalBtn = document.getElementById("closeModal");
        const exploreButtons = document.querySelectorAll(".explore-btn");
        
        // Open modal when Explore More buttons are clicked
        exploreButtons.forEach(button => {
            button.addEventListener("click", () => {
                modal.style.display = "flex";
            });
        });
        
        // Close modal when X is clicked
        closeModalBtn.addEventListener("click", () => {
            modal.style.display = "none";
        });
        
        // Close modal when clicking outside the modal content
        window.addEventListener("click", (event) => {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    </script>
</body>

</html>
