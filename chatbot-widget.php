<?php if(isset($_SESSION['user_id'])){ ?>

<div id="chatbot-button">💬</div>

<div id="chatbot-window">

<div class="chat-header">
RaktaBindu Assistant
<span onclick="toggleChat()">✕</span>
</div>

<div id="chat-messages"></div>

<div class="chat-input">
<input type="text" id="chat-text" placeholder="Ask about blood donation...">
<button onclick="sendMessage()">Send</button>
</div>

</div>

<style>

#chatbot-button{
position:fixed;
bottom:25px;
right:25px;
width:60px;
height:60px;
background:#c62828;
color:white;
font-size:26px;
display:flex;
align-items:center;
justify-content:center;
border-radius:50%;
cursor:pointer;
z-index:9999;
}

#chatbot-window{
position:fixed;
bottom:100px;
right:25px;
width:320px;
background:white;
border-radius:10px;
box-shadow:0 10px 30px rgba(0,0,0,.2);
display:none;
flex-direction:column;
overflow:hidden;
z-index:9999;
}

.chat-header{
background:#c62828;
color:white;
padding:10px;
display:flex;
justify-content:space-between;
}

#chat-messages{
height:300px;
overflow:auto;
padding:10px;
}

.msg-user{
text-align:right;
margin:8px;
}

.msg-bot{
text-align:left;
margin:8px;
}

.msg-user span{
background:#c62828;
color:white;
padding:6px 10px;
border-radius:10px;
}

.msg-bot span{
background:#eee;
padding:6px 10px;
border-radius:10px;
}

.chat-input{
display:flex;
border-top:1px solid #ddd;
}

.chat-input input{
flex:1;
padding:8px;
border:none;
}

.chat-input button{
background:#c62828;
color:white;
border:none;
padding:8px 12px;
cursor:pointer;
}

</style>

<script>

const chatBtn=document.getElementById("chatbot-button");
const chatWindow=document.getElementById("chatbot-window");

chatBtn.onclick=toggleChat;

function toggleChat(){

if(chatWindow.style.display==="flex")
chatWindow.style.display="none";
else
chatWindow.style.display="flex";

}

function addMessage(text,type){

const div=document.createElement("div");

div.className= type==="user" ? "msg-user":"msg-bot";

div.innerHTML="<span>"+text+"</span>";

document.getElementById("chat-messages").appendChild(div);

}

function sendMessage(){

const input=document.getElementById("chat-text");

const msg=input.value;

if(msg==="") return;

addMessage(msg,"user");

input.value="";

fetch("chatbot-api.php",{

method:"POST",

headers:{
"Content-Type":"application/x-www-form-urlencoded"
},

body:"message="+encodeURIComponent(msg)

})

.then(res=>res.json())

.then(data=>{
addMessage(data.reply,"bot");
});

}

</script>

<?php } ?>