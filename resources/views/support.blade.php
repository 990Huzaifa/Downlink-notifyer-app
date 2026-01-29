<!DOCTYPE html>
<html lang="en">
    <body>
    <style>
        * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        }

        body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        }

        .container {
        width: 100%;
        max-width: 1200px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        }

        .content-wrapper {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        min-height: 600px;
        }

        .info-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 60px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        }

        .info-section h1 {
        font-size: 36px;
        margin-bottom: 30px;
        font-weight: 700;
        line-height: 1.2;
        }

        .info-item {
        margin-bottom: 40px;
        }

        .info-item h3 {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 12px;
        font-weight: 600;
        opacity: 0.9;
        }

        .info-item p {
        font-size: 16px;
        line-height: 1.6;
        opacity: 0.95;
        }

        .info-item a {
        color: #ffffff;
        text-decoration: none;
        border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        transition: all 0.3s ease;
        }

        .info-item a:hover {
        border-bottom-color: white;
        opacity: 1;
        }

        .social-links {
        display: flex;
        gap: 20px;
        margin-top: 20px;
        }

        .social-links a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
        text-decoration: none;
        font-size: 18px;
        transition: all 0.3s ease;
        }

        .social-links a:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-3px);
        }

        .form-section {
        padding: 60px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        }

        .form-section h2 {
        font-size: 28px;
        margin-bottom: 30px;
        color: #333;
        font-weight: 700;
        }

        .form-group {
        margin-bottom: 24px;
        }

        .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s ease;
        background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
        resize: vertical;
        min-height: 120px;
        }

        .submit-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 16px;
        }

        .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:active {
        transform: translateY(0);
        }

        .success-message {
        display: none;
        background: #d4edda;
        color: #155724;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px solid #c3e6cb;
        }

        .success-message.show {
        display: block;
        animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
        }

        @media (max-width: 768px) {
        .content-wrapper {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .info-section {
            padding: 40px 30px;
            order: 2;
        }

        .form-section {
            padding: 40px 30px;
            order: 1;
        }

        .info-section h1 {
            font-size: 28px;
        }

        .form-section h2 {
            font-size: 24px;
        }

        .info-item {
            margin-bottom: 30px;
        }

        body {
            padding: 15px;
        }
        }

        @media (max-width: 480px) {
        .info-section,
        .form-section {
            padding: 30px 20px;
        }

        .info-section h1 {
            font-size: 24px;
        }

        .form-section h2 {
            font-size: 20px;
        }

        .social-links {
            gap: 15px;
        }

        .social-links a {
            width: 36px;
            height: 36px;
        }
        }
    </style>

    <div className="container">
        <div className="content-wrapper">
        <div className="form-section">
            <h2>Send us a Message</h2>
            <div className="success-message" id="successMsg">
            ✓ Thank you! We've received your message and will get back to you soon.
            </div>
            <form id="contactForm">
            <div className="form-group">
                <label htmlFor="name">Full Name</label>
                <input
                type="text"
                id="name"
                name="name"
                placeholder="John Doe"
                required
                />
            </div>

            <div className="form-group">
                <label htmlFor="email">Email Address</label>
                <input
                type="email"
                id="email"
                name="email"
                placeholder="john@example.com"
                required
                />
            </div>

            <div className="form-group">
                <label htmlFor="subject">Subject</label>
                <input
                type="text"
                id="subject"
                name="subject"
                placeholder="How can we help?"
                required
                />
            </div>

            <div className="form-group">
                <label htmlFor="message">Message</label>
                <textarea
                id="message"
                name="message"
                placeholder="Tell us more about your inquiry..."
                required
                ></textarea>
            </div>

            <button type="submit" className="submit-btn">
                Send Message
            </button>
            </form>
        </div>

        <div className="info-section">
            <h1>Get in Touch</h1>

            <div className="info-item">
            <h3>Email</h3>
            <p>
                <a href="mailto:hello@company.com">hello@company.com</a>
            </p>
            </div>

            <div className="info-item">
            <h3>Phone</h3>
            <p>
                <a href="tel:+1234567890">+1 (234) 567-890</a>
            </p>
            </div>

            <div className="info-item">
            <h3>Address</h3>
            <p>
                123 Business Street<br />
                New York, NY 10001<br />
                United States
            </p>
            </div>

            <div className="info-item">
            <h3>Business Hours</h3>
            <p>
                Monday - Friday: 9:00 AM - 6:00 PM EST<br />
                Saturday - Sunday: Closed
            </p>
            </div>

            <div className="info-item">
            <h3>Follow Us</h3>
            <div className="social-links">
                <a href="https://twitter.com" title="Twitter">
                𝕏
                </a>
                <a href="https://linkedin.com" title="LinkedIn">
                in
                </a>
                <a href="https://facebook.com" title="Facebook">
                f
                </a>
                <a href="https://instagram.com" title="Instagram">
                ✱
                </a>
            </div>
            </div>
        </div>
        </div>
    </div>
        <!-- JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
        const form = document.getElementById('contactForm');
        const successMsg = document.getElementById('successMsg');

        form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show success message
        successMsg.classList.add('show');
        
        // Reset form
        form.reset();
        
        // Hide success message after 5 seconds
        setTimeout(() => {
            successMsg.classList.remove('show');
        }, 5000);
        });
    </script>


    <script>
        $(document).ready(function(){
            $("#contactForm").on("submit", function(e){
                e.preventDefault();

                let formData = new FormData(this);
                
                $.ajax({
                    url: "/",  // Update with the correct route
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#responseMessage').html('<div class="alert alert-success">Your message has been sent successfully!</div>').show();
                        $('#contactForm')[0].reset();
                    },
                    error: function(xhr, status, error) {
                        $('#responseMessage').html('<div class="alert alert-danger">There was an error submitting your message. Please try again.</div>').show();
                    }
                });
            });
        });
    </script>
    </body>
</html>


