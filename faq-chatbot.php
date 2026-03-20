<?php
session_start();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userName = trim((string)($_SESSION['user_name'] ?? ''));
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ & Safety Chatbot | RaktaBindu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

    <style>
        :root{
            --primary:#c62828;
            --primary-dark:#a61b1b;
            --primary-soft:#fff1f1;
            --green:#157347;
            --green-soft:#ecfdf3;
            --yellow:#b54708;
            --yellow-soft:#fff7e6;
            --blue:#175cd3;
            --blue-soft:#eff8ff;
            --text:#101828;
            --muted:#667085;
            --border:#eaecf0;
            --bg:#f8fafc;
            --card:#ffffff;
            --shadow:0 14px 35px rgba(16,24,40,.08);
            --radius:22px;
        }

        *{box-sizing:border-box;margin:0;padding:0}

        body{
            font-family:"Segoe UI",system-ui,Arial,sans-serif;
            background:var(--bg);
            color:var(--text);
        }

        .page-wrap{
            width:min(1200px,92%);
            margin:26px auto 40px;
        }

        .hero{
            border-radius:30px;
            background:linear-gradient(135deg,#d32f2f 0%,#c62828 55%,#a61b1b 100%);
            color:#fff;
            overflow:hidden;
            position:relative;
            box-shadow:0 22px 45px rgba(198,40,40,.18);
            margin-bottom:22px;
        }

        .hero::before{
            content:"";
            position:absolute;
            right:-70px;
            top:-70px;
            width:220px;
            height:220px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
        }

        .hero::after{
            content:"";
            position:absolute;
            right:120px;
            bottom:-65px;
            width:150px;
            height:150px;
            border-radius:50%;
            background:rgba(255,255,255,.08);
        }

        .hero-inner{
            position:relative;
            padding:30px;
            display:flex;
            justify-content:space-between;
            gap:20px;
            flex-wrap:wrap;
            align-items:flex-start;
        }

        .hero h1{
            font-size:30px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:10px;
        }

        .hero p{
            max-width:64ch;
            line-height:1.8;
            font-size:14px;
            opacity:.96;
        }

        .hero-badges{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .hero-badge{
            padding:10px 14px;
            border-radius:999px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.16);
            font-size:13px;
            font-weight:900;
            white-space:nowrap;
        }

        .layout{
            display:grid;
            grid-template-columns:320px 1fr;
            gap:20px;
            align-items:start;
        }

        .card{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
        }

        .side-card{
            padding:20px;
        }

        .side-title{
            font-size:17px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:10px;
            margin-bottom:8px;
        }

        .side-title i{color:var(--primary)}

        .side-sub{
            font-size:13px;
            color:var(--muted);
            line-height:1.7;
            margin-bottom:16px;
        }

        .topic-list{
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .topic-btn{
            width:100%;
            text-align:left;
            border:1px solid var(--border);
            background:#fff;
            border-radius:14px;
            padding:13px 14px;
            font-size:13px;
            font-weight:800;
            color:#344054;
            cursor:pointer;
            transition:.2s ease;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .topic-btn i{
            width:30px;
            height:30px;
            border-radius:10px;
            display:grid;
            place-items:center;
            background:var(--primary-soft);
            color:var(--primary);
            flex:0 0 auto;
        }

        .topic-btn:hover{
            background:#fcfcfd;
            border-color:#d0d5dd;
            transform:translateY(-1px);
        }

        .tip-list{
            display:flex;
            flex-direction:column;
            gap:12px;
            margin-top:16px;
        }

        .tip-item{
            display:flex;
            gap:10px;
            align-items:flex-start;
            font-size:13px;
            line-height:1.7;
            color:#344054;
        }

        .tip-icon{
            width:34px;
            height:34px;
            border-radius:12px;
            display:grid;
            place-items:center;
            background:var(--green-soft);
            color:var(--green);
            flex:0 0 auto;
        }

        .chat-card{
            overflow:hidden;
        }

        .chat-top{
            padding:18px 20px;
            border-bottom:1px solid var(--border);
            background:#fff;
            display:flex;
            justify-content:space-between;
            gap:16px;
            align-items:center;
            flex-wrap:wrap;
        }

        .chat-top-left h2{
            font-size:18px;
            font-weight:1000;
            display:flex;
            align-items:center;
            gap:10px;
        }

        .chat-top-left h2 i{color:var(--primary)}

        .chat-top-left p{
            margin-top:6px;
            color:var(--muted);
            font-size:13px;
        }

        .chat-status{
            padding:8px 12px;
            border-radius:999px;
            background:var(--green-soft);
            color:var(--green);
            font-size:12px;
            font-weight:900;
            border:1px solid #b7ebc6;
        }

        .quick-bar{
            padding:14px 20px;
            border-bottom:1px solid var(--border);
            background:#fcfcfd;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .quick-chip{
            border:none;
            background:#fff;
            border:1px solid var(--border);
            border-radius:999px;
            padding:10px 14px;
            font-size:12px;
            font-weight:900;
            cursor:pointer;
            color:#344054;
            transition:.2s ease;
        }

        .quick-chip:hover{
            border-color:#f3b2b2;
            background:var(--primary-soft);
            color:var(--primary);
        }

        .chat-box{
            background:linear-gradient(180deg,#fff 0%,#fcfcfd 100%);
            height:560px;
            overflow-y:auto;
            padding:20px;
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .message-row{
            display:flex;
        }

        .message-row.user{
            justify-content:flex-end;
        }

        .message{
            max-width:min(78%, 620px);
            padding:14px 16px;
            border-radius:18px;
            line-height:1.7;
            font-size:14px;
            box-shadow:0 6px 15px rgba(16,24,40,.04);
            white-space:pre-wrap;
        }

        .message.bot{
            background:#fff;
            border:1px solid var(--border);
            color:#344054;
            border-top-left-radius:8px;
        }

        .message.user{
            background:var(--primary);
            color:#fff;
            border-top-right-radius:8px;
        }

        .message small{
            display:block;
            margin-top:8px;
            opacity:.8;
            font-size:11px;
        }

        .typing{
            display:none;
            align-items:center;
            gap:8px;
            color:var(--muted);
            font-size:13px;
            padding:0 20px 14px;
        }

        .typing-dots{
            display:flex;
            gap:4px;
        }

        .typing-dots span{
            width:8px;
            height:8px;
            border-radius:50%;
            background:#d0d5dd;
            animation:bounce 1s infinite ease-in-out;
        }

        .typing-dots span:nth-child(2){animation-delay:.15s}
        .typing-dots span:nth-child(3){animation-delay:.30s}

        @keyframes bounce{
            0%,80%,100%{transform:scale(.8);opacity:.5}
            40%{transform:scale(1);opacity:1}
        }

        .chat-input-wrap{
            border-top:1px solid var(--border);
            background:#fff;
            padding:16px 20px 20px;
        }

        .chat-form{
            display:flex;
            gap:10px;
            align-items:center;
        }

        .chat-form input{
            flex:1;
            height:50px;
            border:1px solid #d0d5dd;
            border-radius:14px;
            padding:0 16px;
            font-size:14px;
            outline:none;
            color:var(--text);
        }

        .chat-form input:focus{
            border-color:#f04438;
            box-shadow:0 0 0 4px rgba(240,68,56,.10);
        }

        .send-btn, .clear-btn{
            height:50px;
            border:none;
            border-radius:14px;
            padding:0 18px;
            font-size:13px;
            font-weight:1000;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            transition:.2s ease;
        }

        .send-btn{
            background:var(--primary);
            color:#fff;
        }

        .send-btn:hover{background:var(--primary-dark)}

        .clear-btn{
            background:#fff;
            color:#344054;
            border:1px solid var(--border);
        }

        .clear-btn:hover{
            background:#f9fafb;
        }

        .note{
            margin-top:12px;
            color:var(--muted);
            font-size:12px;
            line-height:1.7;
        }

        @media (max-width: 980px){
            .layout{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 620px){
            .chat-box{
                height:480px;
            }

            .chat-form{
                flex-wrap:wrap;
            }

            .chat-form input,
            .send-btn,
            .clear-btn{
                width:100%;
            }

            .message{
                max-width:90%;
            }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page-wrap">

    <section class="hero">
        <div class="hero-inner">
            <div>
                <h1><i class="fa-solid fa-robot"></i> FAQ & Safety Chatbot</h1>
                <p>
                    Get instant help about blood donation eligibility, donation safety, request process,
                    blood group compatibility, donor rewards, and general RaktaBindu guidance.
                </p>
            </div>

            <div class="hero-badges">
                <div class="hero-badge"><i class="fa-solid fa-shield-heart"></i> Safety Guidance</div>
                <div class="hero-badge"><i class="fa-solid fa-droplet"></i> Donation Help</div>
                <div class="hero-badge"><i class="fa-solid fa-circle-question"></i> Quick Answers</div>
            </div>
        </div>
    </section>

    <section class="layout">
        <aside class="card side-card">
            <div class="side-title"><i class="fa-solid fa-layer-group"></i> Help Topics</div>
            <div class="side-sub">Tap a topic to ask the chatbot automatically.</div>

            <div class="topic-list">
                <button class="topic-btn" data-question="Who can donate blood?">
                    <i class="fa-solid fa-user-check"></i> Who can donate blood?
                </button>
                <button class="topic-btn" data-question="How often can I donate blood?">
                    <i class="fa-solid fa-clock-rotate-left"></i> Donation interval
                </button>
                <button class="topic-btn" data-question="Is blood donation safe?">
                    <i class="fa-solid fa-shield-heart"></i> Donation safety
                </button>
                <button class="topic-btn" data-question="What should I do before donation?">
                    <i class="fa-solid fa-glass-water"></i> Before donation
                </button>
                <button class="topic-btn" data-question="What should I do after donation?">
                    <i class="fa-solid fa-bed"></i> After donation
                </button>
                <button class="topic-btn" data-question="How do I request blood in RaktaBindu?">
                    <i class="fa-solid fa-hand-holding-medical"></i> Request blood
                </button>
                <button class="topic-btn" data-question="How does donor matching work?">
                    <i class="fa-solid fa-people-arrows"></i> Donor matching
                </button>
                <button class="topic-btn" data-question="What rewards do donors get?">
                    <i class="fa-solid fa-medal"></i> Rewards and badges
                </button>
                <button class="topic-btn" data-question="Which blood groups are compatible?">
                    <i class="fa-solid fa-vial-circle-check"></i> Blood compatibility
                </button>
                <button class="topic-btn" data-question="How can I contact support?">
                    <i class="fa-solid fa-headset"></i> Contact support
                </button>
            </div>

            <div class="tip-list">
                <div class="tip-item">
                    <div class="tip-icon"><i class="fa-solid fa-lightbulb"></i></div>
                    <div>Ask short and clear questions for better answers.</div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <div>This chatbot gives informational guidance and platform help.</div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon"><i class="fa-solid fa-phone-volume"></i></div>
                    <div>For urgent real-world emergencies, contact a hospital or emergency service directly.</div>
                </div>
            </div>
        </aside>

        <section class="card chat-card">
            <div class="chat-top">
                <div class="chat-top-left">
                    <h2><i class="fa-solid fa-comments"></i> Chat Assistant</h2>
                    <p>
                        <?php if ($isLoggedIn): ?>
                            Welcome<?php echo $userName !== '' ? ', ' . e($userName) : ''; ?>. Ask me anything about RaktaBindu.
                        <?php else: ?>
                            Ask me anything about blood donation and RaktaBindu.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="chat-status">
                    <i class="fa-solid fa-circle-check"></i> Assistant Ready
                </div>
            </div>

            <div class="quick-bar">
                <button class="quick-chip" data-question="Who can donate blood?">Eligibility</button>
                <button class="quick-chip" data-question="Is blood donation safe?">Safety</button>
                <button class="quick-chip" data-question="How often can I donate blood?">Interval</button>
                <button class="quick-chip" data-question="How do I request blood in RaktaBindu?">Request Help</button>
                <button class="quick-chip" data-question="What rewards do donors get?">Rewards</button>
                <button class="quick-chip" data-question="Which blood groups are compatible?">Compatibility</button>
            </div>

            <div class="chat-box" id="chatBox">
                <div class="message-row">
                    <div class="message bot">
Hello 👋 I’m the RaktaBindu FAQ & Safety Chatbot.

You can ask me about:
• blood donation eligibility
• donation safety
• blood request process
• donor matching
• reward badges and points
• blood group compatibility

Try asking: “Who can donate blood?”
                        <small>RaktaBindu Assistant</small>
                    </div>
                </div>
            </div>

            <div class="typing" id="typingIndicator">
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
                <span>Assistant is typing...</span>
            </div>

            <div class="chat-input-wrap">
                <form class="chat-form" id="chatForm">
                    <input
                        type="text"
                        id="userInput"
                        placeholder="Type your question here..."
                        autocomplete="off"
                    >
                    <button type="submit" class="send-btn">
                        <i class="fa-solid fa-paper-plane"></i> Send
                    </button>
                    <button type="button" class="clear-btn" id="clearChatBtn">
                        <i class="fa-solid fa-trash-can"></i> Clear
                    </button>
                </form>

                <div class="note">
                    This chatbot is for FAQ and guidance purposes. It does not replace medical advice from a qualified professional.
                </div>
            </div>
        </section>
    </section>
</div>

<script>
    const chatBox = document.getElementById("chatBox");
    const userInput = document.getElementById("userInput");
    const chatForm = document.getElementById("chatForm");
    const typingIndicator = document.getElementById("typingIndicator");
    const clearChatBtn = document.getElementById("clearChatBtn");

    const botRules = [
        {
            keywords: ["who can donate", "eligibility", "eligible", "can donate", "requirements"],
            answer:
`A person can usually donate blood if they:
• are between 18 and 65 years old
• weigh at least 50 kg
• are in good general health
• have not donated in the last 3 months
• are not currently ill

The final decision should still be confirmed during real screening at the hospital or blood center.`
        },
        {
            keywords: ["how often", "interval", "how many months", "donate again", "90 days"],
            answer:
`Whole blood donation is generally allowed every 3 months (90 days).

RaktaBindu also uses this interval in donor guidance so donors do not schedule too frequently.`
        },
        {
            keywords: ["safe", "safety", "dangerous", "is it safe", "risk"],
            answer:
`Blood donation is generally safe when done at a proper medical facility.

Important safety points:
• sterile equipment is used
• trained medical staff supervise the process
• you may feel temporary weakness or dizziness
• drink water and rest after donation

If you feel unwell, do not donate and consult a healthcare professional.`
        },
        {
            keywords: ["before donation", "prepare", "before donating", "eat before", "what should i do before"],
            answer:
`Before donation:
• drink plenty of water
• eat a proper meal
• sleep well the night before
• avoid alcohol and smoking
• carry a valid ID if required
• do not donate if you are feeling sick`
        },
        {
            keywords: ["after donation", "after donating", "what should i do after"],
            answer:
`After donation:
• rest for a few minutes
• drink fluids
• eat light nutritious food
• avoid heavy exercise for the day
• keep the bandage on for some time
• contact medical staff if you feel faint or unwell`
        },
        {
            keywords: ["request blood", "how do i request", "request process", "need blood", "blood request"],
            answer:
`To request blood in RaktaBindu:
1. Log in to your account
2. Open the blood request page
3. Enter blood group, hospital/location, urgency, quantity, date, and notes
4. Submit the request
5. Matching donors can then review and accept it

Once accepted, the donor and requester can coordinate further.`
        },
        {
            keywords: ["matching", "match donor", "donor matching", "how does donor matching work"],
            answer:
`RaktaBindu matches donors mainly using:
• blood group compatibility
• open request status
• donor response history
• hospital or location details

A donor can accept or decline a matching request. Accepted requests then show requester contact information for coordination.`
        },
        {
            keywords: ["reward", "badge", "points", "stars", "gamification"],
            answer:
`In RaktaBindu, donors can earn:
• reward points
• stars
• badges such as First Drop, Life Saver, Hero Donor, Gold Donor, and more

These rewards should be granted after a donation is actually completed and verified, not just when a request is accepted.`
        },
        {
            keywords: ["blood group", "compatible", "compatibility", "which blood groups"],
            answer:
`Basic blood group compatibility:
• O- can donate to all groups
• AB+ can receive from all groups
• A can usually receive from A and O
• B can usually receive from B and O
• AB can receive from A, B, AB, and O

For real transfusion decisions, hospital verification is always required.`
        },
        {
            keywords: ["support", "help", "contact support", "customer support"],
            answer:
`You can contact RaktaBindu support through the support details shown on the website.

Typical support areas:
• account help
• donation scheduling issues
• request visibility problems
• reward questions
• platform guidance`
        },
        {
            keywords: ["login", "signup", "account", "password"],
            answer:
`For account help:
• use signup to create an account
• use login with your registered email
• use forgot password if that feature is enabled
• make sure your account is verified if email verification is required`
        },
        {
            keywords: ["hospital", "admin", "verify donation", "complete donation"],
            answer:
`Hospitals or admins should verify donation completion in the system.

That verification step is important because it can:
• mark donation status as Completed
• update donor history
• grant donor points, stars, and badges`
        }
    ];

    function normalize(text) {
        return text.toLowerCase().trim();
    }

    function findAnswer(question) {
        const q = normalize(question);

        for (const rule of botRules) {
            for (const keyword of rule.keywords) {
                if (q.includes(keyword)) {
                    return rule.answer;
                }
            }
        }

        return `Sorry, I could not find an exact answer for that.

You can try asking about:
• who can donate blood
• donation safety
• how often to donate
• how to request blood
• donor rewards
• blood group compatibility`;
    }

    function createMessageRow(text, type, label = "") {
        const row = document.createElement("div");
        row.className = "message-row" + (type === "user" ? " user" : "");

        const msg = document.createElement("div");
        msg.className = "message " + type;
        msg.textContent = text;

        if (label) {
            const small = document.createElement("small");
            small.textContent = label;
            msg.appendChild(small);
        }

        row.appendChild(msg);
        return row;
    }

    function addUserMessage(text) {
        const row = createMessageRow(text, "user", "You");
        chatBox.appendChild(row);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function addBotMessage(text) {
        const row = createMessageRow(text, "bot", "RaktaBindu Assistant");
        chatBox.appendChild(row);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    function showTyping(show) {
        typingIndicator.style.display = show ? "flex" : "none";
        if (show) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    function sendQuestion(text) {
        const question = text.trim();
        if (!question) return;

        addUserMessage(question);
        userInput.value = "";
        showTyping(true);

        setTimeout(() => {
            showTyping(false);
            const answer = findAnswer(question);
            addBotMessage(answer);
        }, 500);
    }

    chatForm.addEventListener("submit", function(e) {
        e.preventDefault();
        sendQuestion(userInput.value);
    });

    document.querySelectorAll("[data-question]").forEach(btn => {
        btn.addEventListener("click", function() {
            sendQuestion(this.getAttribute("data-question"));
        });
    });

    clearChatBtn.addEventListener("click", function() {
        chatBox.innerHTML = `
            <div class="message-row">
                <div class="message bot">
Hello 👋 I’m the RaktaBindu FAQ & Safety Chatbot.

You can ask me about:
• blood donation eligibility
• donation safety
• blood request process
• donor matching
• reward badges and points
• blood group compatibility

Try asking: “Who can donate blood?”
                    <small>RaktaBindu Assistant</small>
                </div>
            </div>
        `;
        userInput.value = "";
        userInput.focus();
    });
</script>

</body>
</html>