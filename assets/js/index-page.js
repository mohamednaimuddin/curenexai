(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function setupMobileMenu() {
        var menuBtn = byId('mobileMenuBtn');
        var mobileMenu = byId('mobileMenu');
        if (!menuBtn || !mobileMenu) return;

        menuBtn.addEventListener('click', function () {
            mobileMenu.classList.toggle('active');
            var icon = menuBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });

        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                var href = this.getAttribute('href');
                var target = href ? document.querySelector(href) : null;
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                mobileMenu.classList.remove('active');
                var icon = menuBtn.querySelector('i');
                if (icon) {
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-times');
                }
            });
        });
    }

    function setupNavScrollState() {
        var nav = document.querySelector('.landing-nav');
        if (!nav) return;
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }, { passive: true });
    }

    function setupScrollAnimations() {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) entry.target.classList.add('animate-in');
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('.feature-card, .step-card, .about-feature, .contact-item, .feedback-item').forEach(function (el) {
            observer.observe(el);
        });

        var scrollObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('scroll-visible');
                scrollObserver.unobserve(entry.target);
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -80px 0px'
        });

        document.querySelectorAll(
            '.scroll-slide-right, .scroll-slide-left, .scroll-stagger, ' +
            '.about-content, .contact-wrapper, .cta-content, ' +
            '.scroll-3d-flip, .scroll-3d-rotate-left, .scroll-3d-rotate-right, ' +
            '.scroll-3d-zoom, .scroll-3d-stagger'
        ).forEach(function (el) {
            scrollObserver.observe(el);
        });
    }

    function setup3DEffects() {
        var isMobile = window.innerWidth <= 768;
        var isSlowConnection = 'connection' in navigator && (
            navigator.connection.saveData ||
            navigator.connection.effectiveType === '2g' ||
            navigator.connection.effectiveType === 'slow-2g' ||
            navigator.connection.effectiveType === '3g'
        );
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (isMobile || isSlowConnection || prefersReducedMotion) {
            document.querySelectorAll('.geo-shape, .glow-orb').forEach(function (el) {
                el.style.display = 'none';
            });
            return;
        }

        var geoShapes = document.querySelectorAll('.geo-shape');
        var glowOrbs = document.querySelectorAll('.glow-orb');
        var featureCards = document.querySelectorAll('.feature-card');
        var parallaxSections = document.querySelectorAll('.features-section, .about-section, .how-it-works-section, .feedback-section, .cta-section');

        var scrollY = 0;
        var ticking = false;
        var viewportHeight = window.innerHeight;
        var maxScroll = 0;

        function updateViewportCache() {
            viewportHeight = window.innerHeight;
            maxScroll = document.documentElement.scrollHeight - viewportHeight;
        }

        function updateAllEffects() {
            var sectionData = [];
            parallaxSections.forEach(function (section) {
                var rect = section.getBoundingClientRect();
                sectionData.push({ section: section, top: rect.top, height: rect.height });
            });

            geoShapes.forEach(function (shape, idx) {
                var speed = parseFloat(shape.dataset.speed || '0.3');
                var rotate = parseFloat(shape.dataset.rotate || '0');
                var y = scrollY * speed * -0.5;
                var rx = scrollY * rotate * 0.08;
                var ry = scrollY * rotate * 0.12;
                var rz = scrollY * rotate * 0.05;
                shape.style.transform = 'translate3d(0,' + y + 'px,0) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) rotateZ(' + rz + 'deg)';

                var progress = maxScroll > 0 ? scrollY / maxScroll : 0;
                var phase = (progress * 3 + idx * 0.3) % 1;
                shape.style.opacity = String(0.04 + Math.sin(phase * Math.PI) * 0.06);
            });

            glowOrbs.forEach(function (orb) {
                var speed = parseFloat(orb.dataset.speed || '0.1');
                orb.style.transform = 'translate3d(0,' + (scrollY * speed * -0.3) + 'px,0)';
            });

            var viewportCenter = viewportHeight / 2;
            sectionData.forEach(function (item) {
                var sectionCenter = item.top + item.height / 2;
                var distance = (sectionCenter - viewportCenter) / viewportHeight;
                var rotateX = distance * 1.5;
                var translateZ = Math.abs(distance) * -15;
                item.section.style.transform = 'perspective(1200px) rotateX(' + rotateX + 'deg) translateZ(' + translateZ + 'px)';
            });

            ticking = false;
        }

        updateViewportCache();
        window.addEventListener('resize', updateViewportCache, { passive: true });
        window.addEventListener('scroll', function () {
            scrollY = window.pageYOffset;
            if (!ticking) {
                requestAnimationFrame(updateAllEffects);
                ticking = true;
            }
        }, { passive: true });

        featureCards.forEach(function (card) {
            var cardRect = null;
            card.addEventListener('mouseenter', function () {
                cardRect = card.getBoundingClientRect();
            });
            card.addEventListener('mousemove', function (e) {
                if (!cardRect) return;
                var x = e.clientX - cardRect.left;
                var y = e.clientY - cardRect.top;
                var rotateY = ((x - cardRect.width / 2) / (cardRect.width / 2)) * 8;
                var rotateX = ((cardRect.height / 2 - y) / (cardRect.height / 2)) * 8;
                card.style.setProperty('--rotateX', rotateX + 'deg');
                card.style.setProperty('--rotateY', rotateY + 'deg');
            });
            card.addEventListener('mouseleave', function () {
                cardRect = null;
                card.style.setProperty('--rotateX', '0deg');
                card.style.setProperty('--rotateY', '0deg');
            });
        });

        requestAnimationFrame(updateAllEffects);
    }

    function setupLoaderDismiss() {
        var loader = byId('pageLoader');
        if (!loader) return;

        function dismissLoader() {
            loader.classList.add('loaded');
            setTimeout(function () {
                if (loader.parentNode) loader.parentNode.removeChild(loader);
            }, 700);
        }

        var ready = false;
        var timerDone = false;

        window.addEventListener('load', function () {
            ready = true;
            if (timerDone) dismissLoader();
        });

        setTimeout(function () {
            timerDone = true;
            if (ready) dismissLoader();
        }, 2800);

        setTimeout(dismissLoader, 6000);
    }

    function setupGratitudeModal() {
        function showGratitudeModal() {
            var modal = byId('gratitudeModal');
            if (!modal) return;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeGratitudeModal() {
            var modal = byId('gratitudeModal');
            if (!modal) return;
            modal.classList.remove('active');
            document.body.style.overflow = '';
            sessionStorage.setItem('gratitudeShown', 'true');
        }

        window.showGratitudeModal = showGratitudeModal;
        window.closeGratitudeModal = closeGratitudeModal;

        if (!sessionStorage.getItem('gratitudeShown')) {
            setTimeout(showGratitudeModal, 500);
        }

        var overlay = document.querySelector('.gratitude-overlay');
        if (overlay) overlay.addEventListener('click', closeGratitudeModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeGratitudeModal();
        });
    }

    function setupChatbot() {
        var chatbotWidget = byId('chatbot-widget');
        var chatbotToggle = byId('chatbot-toggle');
        var chatbotMessages = byId('chatbot-messages');
        var chatbotForm = byId('chatbot-form');
        var chatbotInput = byId('chatbot-input');
        var chatbotSend = byId('chatbot-send');
        var chatbotTyping = byId('chatbot-typing');
        var chatbotClear = byId('chatbot-clear');
        var chatbotMinimize = byId('chatbot-minimize');

        if (!chatbotWidget || !chatbotToggle || !chatbotMessages || !chatbotForm || !chatbotInput || !chatbotSend || !chatbotTyping || !chatbotClear || !chatbotMinimize) {
            return;
        }

        var appUrl = (document.body && document.body.dataset && document.body.dataset.appUrl) ? document.body.dataset.appUrl : '';
        var conversationHistory = [];
        var isProcessing = false;

        function openChat() {
            chatbotWidget.classList.add('open');
            sessionStorage.setItem('chatbot_open', 'true');
            sessionStorage.setItem('chatbot_interacted', 'true');
            chatbotInput.focus();
            scrollToBottom();
            if (window.innerWidth <= 480) document.body.style.overflow = 'hidden';
        }

        function closeChat() {
            chatbotWidget.classList.remove('open');
            sessionStorage.setItem('chatbot_open', 'false');
            document.body.style.overflow = '';
        }

        function toggleChat() {
            if (chatbotWidget.classList.contains('open')) closeChat();
            else openChat();
        }

        function updateSendButton() {
            chatbotSend.disabled = chatbotInput.value.trim().length === 0 || isProcessing;
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function scrollToBottom() {
            setTimeout(function () {
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            }, 100);
        }

        function saveHistory() {
            if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);
            sessionStorage.setItem('chatbot_history', JSON.stringify(conversationHistory));
        }

        function addMessageToUI(content, isUser) {
            var messageDiv = document.createElement('div');
            messageDiv.className = 'chatbot-message ' + (isUser ? 'user' : 'bot');
            messageDiv.innerHTML =
                '<div class="chatbot-message-avatar"><i class="fas fa-' + (isUser ? 'user' : 'robot') + '"></i></div>' +
                '<div class="chatbot-message-content"><p>' + escapeHtml(content) + '</p></div>';
            chatbotMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function renderSavedMessages() {
            if (conversationHistory.length === 0) return;
            chatbotMessages.innerHTML = '';
            conversationHistory.forEach(function (msg) {
                addMessageToUI(msg.content, msg.role === 'user');
            });
        }

        function clearChat() {
            if (!confirm('Clear chat history?')) return;
            conversationHistory = [];
            sessionStorage.removeItem('chatbot_history');
            chatbotMessages.innerHTML =
                '<div class="chatbot-welcome">' +
                    '<div class="chatbot-welcome-icon"><i class="fas fa-leaf"></i></div>' +
                    '<h3>Welcome to Curenex AI!</h3>' +
                    '<p>I am CurenexBot, your AI guide. Ask me anything about our platform, homeopathy, or how we can help your practice.</p>' +
                '</div>' +
                '<div class="chatbot-message bot">' +
                    '<div class="chatbot-message-avatar"><i class="fas fa-robot"></i></div>' +
                    '<div class="chatbot-message-content"><p>Hi there! I am CurenexBot, here to help you explore our platform. What would you like to know?</p></div>' +
                '</div>' +
                '<div class="chatbot-suggestions" id="chatbot-suggestions">' +
                    '<button class="chatbot-suggestion" data-message="What features do you offer?"><i class="fas fa-star"></i> Features</button>' +
                    '<button class="chatbot-suggestion" data-message="How does the AI diagnosis work?"><i class="fas fa-brain"></i> AI Diagnosis</button>' +
                    '<button class="chatbot-suggestion" data-message="Is it free to use?"><i class="fas fa-gift"></i> Pricing</button>' +
                    '<button class="chatbot-suggestion" data-message="Tell me about the dermo skin analysis feature"><i class="fas fa-hand-holding-medical"></i> Skin Analysis</button>' +
                '</div>';
        }

        async function handleSubmit(e) {
            e.preventDefault();
            var message = chatbotInput.value.trim();
            if (!message || isProcessing) return;

            isProcessing = true;
            chatbotInput.value = '';
            updateSendButton();

            var suggestions = chatbotMessages.querySelector('.chatbot-suggestions');
            if (suggestions) suggestions.style.display = 'none';

            addMessageToUI(message, true);
            conversationHistory.push({ role: 'user', content: message });
            saveHistory();

            chatbotTyping.style.display = 'flex';
            scrollToBottom();

            try {
                var response = await fetch(appUrl + '/api/chatbot.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: message, history: conversationHistory.slice(-10) })
                });
                var data = await response.json();
                chatbotTyping.style.display = 'none';

                if (data.success && data.message) {
                    addMessageToUI(data.message, false);
                    conversationHistory.push({ role: 'assistant', content: data.message });
                    saveHistory();
                } else {
                    addMessageToUI((data && data.message) ? data.message : 'Sorry, I encountered an error. Please try again.', false);
                }
            } catch (error) {
                console.error('Chatbot error:', error);
                chatbotTyping.style.display = 'none';
                addMessageToUI('Sorry, I am having trouble connecting. Please try again later.', false);
            }

            isProcessing = false;
            updateSendButton();
            chatbotInput.focus();
        }

        var saved = sessionStorage.getItem('chatbot_history');
        if (saved) {
            try {
                conversationHistory = JSON.parse(saved);
                renderSavedMessages();
            } catch (e) {
                console.error('Failed to load chat history');
            }
        }

        if (sessionStorage.getItem('chatbot_open') === 'true') openChat();

        chatbotToggle.addEventListener('click', toggleChat);
        chatbotMinimize.addEventListener('click', closeChat);
        chatbotClear.addEventListener('click', clearChat);
        chatbotForm.addEventListener('submit', handleSubmit);
        chatbotInput.addEventListener('input', updateSendButton);

        chatbotMessages.addEventListener('click', function (e) {
            var btn = e.target.closest('.chatbot-suggestion');
            if (!btn) return;
            chatbotInput.value = btn.dataset.message;
            handleSubmit(e);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && chatbotWidget.classList.contains('open')) closeChat();
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 480) document.body.style.overflow = '';
            else if (chatbotWidget.classList.contains('open')) document.body.style.overflow = 'hidden';
        }, { passive: true });

        if (!sessionStorage.getItem('chatbot_interacted')) {
            setTimeout(function () {
                if (chatbotWidget.classList.contains('open')) return;
                chatbotToggle.style.animation = 'wiggle 0.5s ease 3';
                setTimeout(function () {
                    chatbotToggle.style.animation = '';
                }, 1500);
            }, 5000);
        }

        updateSendButton();
    }

    function setupEmailObfuscation() {
        function decodeEmail(el) {
            var u = el.getAttribute('data-user');
            var d = el.getAttribute('data-domain');
            if (!u || !d) return;
            var addr = u + '\u0040' + d;
            var a = document.createElement('a');
            a.href = 'mailto:' + addr;
            a.textContent = addr;
            a.rel = 'nofollow';
            el.replaceWith(a);
        }

        document.querySelectorAll('.cx-email').forEach(decodeEmail);
    }

    function appendWiggleKeyframes() {
        if (document.getElementById('chatbot-wiggle-style')) return;
        var style = document.createElement('style');
        style.id = 'chatbot-wiggle-style';
        style.textContent = '@keyframes wiggle{0%,100%{transform:rotate(0)}25%{transform:rotate(-10deg)}75%{transform:rotate(10deg)}}';
        document.head.appendChild(style);
    }

    function init() {
        setupMobileMenu();
        setupNavScrollState();
        setupScrollAnimations();
        setup3DEffects();
        setupLoaderDismiss();
        setupGratitudeModal();
        appendWiggleKeyframes();
        setupChatbot();
        setupEmailObfuscation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
