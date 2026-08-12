            </div> <!-- End container-fluid -->
        </div> <!-- End #content -->
    </div> <!-- End #wrapper -->

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- AI Chat Floating Widget -->
    <button class="ai-chat-launcher" id="aiChatLauncher" title="Ask AI Assistant">
        <i class="fa-solid fa-robot fa-lg"></i>
    </button>

    <div class="ai-chat-box" id="aiChatBox">
        <div class="ai-chat-header">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-robot"></i>
                <div>
                    <h6 class="mb-0">IPMC Campus AI</h6>
                    <span class="badge">Virtual Agent</span>
                </div>
            </div>
            <button class="chat-close-btn" id="aiChatClose" aria-label="Close Chat">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="ai-chat-body" id="aiChatBody">
            <div class="chat-message ai">Loading your secure conversation history...</div>
        </div>

        <div class="ai-chat-footer">
            <form id="aiChatForm" onsubmit="submitAIChat(event)">
                <div class="ai-chat-input-wrapper">
                    <input type="text" class="ai-chat-input" id="aiChatInput" placeholder="Type your question..." autocomplete="off" required>
                    <button type="submit" class="ai-chat-send-btn" id="aiChatSendBtn" aria-label="Send Message">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </form>
            <div class="ai-chat-meta">Powered by IPMC AI • <a href="#" onclick="clearAIChatHistory(event)" class="text-decoration-none">Clear Chat</a></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom jQuery-free Responsive Toggle Script with mobile overlay backdrop support -->
    <script>
        // Global variables for AI chat history persistence across pages
        async function loadAIChatHistory() {
            const body = document.getElementById('aiChatBody');
            if (!body) return;

            try {
                const response = await fetch('ai-chat-api.php?action=load');
                if (response.ok) {
                    const data = await response.json();
                    body.innerHTML = '';

                    if (!data.history || data.history.length === 0) {
                        body.innerHTML = `<div class="chat-message ai">Hello, ${data.user_name}! I am your IPMC Campus AI. How can I assist you with your leaves, attendance, appraisals, or general records today?</div>`;
                    } else {
                        data.history.forEach(msg => {
                            const msgHtml = document.createElement('div');
                            msgHtml.className = `chat-message ${msg.role === 'user' ? 'user' : 'ai'}`;
                            msgHtml.innerText = msg.message;
                            body.appendChild(msgHtml);
                        });
                    }
                } else {
                    body.innerHTML = '<div class="chat-message ai text-danger">Failed to load secure chat history. Please refresh the page.</div>';
                }
            } catch (err) {
                body.innerHTML = '<div class="chat-message ai text-danger">Network error connecting to AI services.</div>';
            } finally {
                body.scrollTop = body.scrollHeight;
            }
        }

        async function clearAIChatHistory(e) {
            if (e) e.preventDefault();
            const body = document.getElementById('aiChatBody');
            const userName = "<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>";

            if (!body) return;
            body.innerHTML = '<div class="chat-message ai">Clearing conversation history...</div>';

            try {
                const response = await fetch('ai-chat-api.php?action=clear', { method: 'POST' });
                if (response.ok) {
                    body.innerHTML = `<div class="chat-message ai">Hello, ${userName}! Chat history cleared. How can I assist you with your records today?</div>`;
                } else {
                    body.innerHTML = '<div class="chat-message ai text-danger">Error clearing chat history.</div>';
                }
            } catch (err) {
                body.innerHTML = '<div class="chat-message ai text-danger">Network error clearing chat history.</div>';
            } finally {
                body.scrollTop = body.scrollHeight;
            }
        }

        async function submitAIChat(e) {
            e.preventDefault();
            const input = document.getElementById('aiChatInput');
            const body = document.getElementById('aiChatBody');
            const sendBtn = document.getElementById('aiChatSendBtn');
            const message = input.value.trim();

            if (!message) return;

            // Render User Bubble
            const userMsgHtml = document.createElement('div');
            userMsgHtml.className = 'chat-message user';
            userMsgHtml.innerText = message;
            body.appendChild(userMsgHtml);
            input.value = '';

            // Auto Scroll
            body.scrollTop = body.scrollHeight;

            // Render Typing Indicator
            const typingHtml = document.createElement('div');
            typingHtml.className = 'ai-typing-indicator';
            typingHtml.id = 'aiTypingIndicator';
            typingHtml.innerHTML = '<span></span><span></span><span></span>';
            body.appendChild(typingHtml);
            body.scrollTop = body.scrollHeight;

            // Disable Send button during operation
            sendBtn.disabled = true;

            try {
                const response = await fetch('ai-chat-api.php?action=send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: message })
                });

                // Remove typing indicator
                const indicator = document.getElementById('aiTypingIndicator');
                if (indicator) indicator.remove();

                if (response.ok) {
                    const data = await response.json();
                    const aiMsgHtml = document.createElement('div');
                    aiMsgHtml.className = 'chat-message ai';
                    aiMsgHtml.innerText = data.reply;
                    body.appendChild(aiMsgHtml);
                } else {
                    const errData = await response.json();
                    const aiMsgHtml = document.createElement('div');
                    aiMsgHtml.className = 'chat-message ai text-danger';
                    aiMsgHtml.innerText = `Error: ${errData.error || 'Server connection error.'}`;
                    body.appendChild(aiMsgHtml);
                }
            } catch (err) {
                const indicator = document.getElementById('aiTypingIndicator');
                if (indicator) indicator.remove();

                const aiMsgHtml = document.createElement('div');
                aiMsgHtml.className = 'chat-message ai text-danger';
                aiMsgHtml.innerText = 'Unable to connect to the AI service. Please try again.';
                body.appendChild(aiMsgHtml);
            } finally {
                sendBtn.disabled = false;
                body.scrollTop = body.scrollHeight;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Load persistent isolated chat history securely from database
            loadAIChatHistory();

            // Launcher and close toggle animations
            const launcher = document.getElementById('aiChatLauncher');
            const chatBox = document.getElementById('aiChatBox');
            const closeBtn = document.getElementById('aiChatClose');

            if (launcher && chatBox) {
                launcher.addEventListener('click', function() {
                    chatBox.classList.toggle('show');
                    if (chatBox.classList.contains('show')) {
                        const body = document.getElementById('aiChatBody');
                        if (body) body.scrollTop = body.scrollHeight;
                    }
                });
            }

            if (closeBtn && chatBox) {
                closeBtn.addEventListener('click', function() {
                    chatBox.classList.remove('show');
                });
            }

            const toggleBtn = document.getElementById('sidebarToggleBtn');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            function toggleSidebar(show) {
                if (show) {
                    sidebar.classList.add('show');
                    if (backdrop) backdrop.classList.add('show');
                } else {
                    sidebar.classList.remove('show');
                    if (backdrop) backdrop.classList.remove('show');
                }
            }

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isShown = sidebar.classList.contains('show');
                    toggleSidebar(!isShown);
                });
            }

            // Close sidebar when clicking outside on mobile (clicking the backdrop overlay)
            if (backdrop) {
                backdrop.addEventListener('click', function () {
                    toggleSidebar(false);
                });
            }

            // Close sidebar when clicking outside on mobile or window resize
            document.addEventListener('click', function (e) {
                if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('show')) {
                    const isClickInsideSidebar = sidebar.contains(e.target);
                    const isClickToggleBtn = toggleBtn && toggleBtn.contains(e.target);

                    if (!isClickInsideSidebar && !isClickToggleBtn) {
                        toggleSidebar(false);
                    }
                }
            });

            // Adjust on window resize
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 992) {
                    if (sidebar) sidebar.classList.remove('show');
                    if (backdrop) backdrop.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
