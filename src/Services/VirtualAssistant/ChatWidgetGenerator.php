<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\VirtualAssistant;

class ChatWidgetGenerator
{
    /**
     * Generates a lightweight, zero-dependency HTML/JS/CSS client-side chat bubble
     * that connects to the Bedrock WordPress REST assistant endpoint.
     */
    public function generateWidgetScript(string $apiEndpoint, string $assistantName = 'Bedrock Assistant'): string
    {
        return <<<HTML
<!-- Bedrock Autonomous Virtual Assistant Widget -->
<div id="bedrock-chat-root" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
  <button id="bedrock-chat-toggle" style="background: #1d2128; color: #fff; border: none; border-radius: 50%; width: 56px; height: 56px; cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.25); display: flex; align-items: center; justify-content: center; font-size: 24px;">💬</button>
  <div id="bedrock-chat-window" style="display: none; width: 360px; height: 520px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.18); flex-direction: column; overflow: hidden; margin-bottom: 12px; border: 1px solid #e2e8f0;">
    <div style="background: #1d2128; color: #fff; padding: 14px 18px; font-weight: 600; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
      <span>{$assistantName}</span>
      <button id="bedrock-chat-close" style="background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 18px;">&times;</button>
    </div>
    <div id="bedrock-chat-messages" style="flex: 1; padding: 16px; overflow-y: auto; background: #f8fafc; font-size: 14px; display: flex; flex-direction: column; gap: 10px;">
      <div style="background: #e2e8f0; color: #1e293b; padding: 10px 14px; border-radius: 10px 10px 10px 2px; max-width: 80%;">
        ¡Hola! ¿En qué puedo ayudarte hoy?
      </div>
    </div>
    <form id="bedrock-chat-form" style="display: flex; border-top: 1px solid #e2e8f0; padding: 10px; background: #fff;">
      <input type="text" id="bedrock-chat-input" placeholder="Escribe tu consulta..." style="flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; outline: none;" required />
      <button type="submit" style="background: #f26419; color: #fff; border: none; border-radius: 6px; margin-left: 8px; padding: 0 14px; cursor: pointer; font-weight: 600;">Enviar</button>
    </form>
  </div>
</div>
<script>
(function() {
  const root = document.getElementById('bedrock-chat-root');
  const toggle = document.getElementById('bedrock-chat-toggle');
  const win = document.getElementById('bedrock-chat-window');
  const close = document.getElementById('bedrock-chat-close');
  const form = document.getElementById('bedrock-chat-form');
  const input = document.getElementById('bedrock-chat-input');
  const msgs = document.getElementById('bedrock-chat-messages');

  toggle.addEventListener('click', () => {
    win.style.display = win.style.display === 'none' ? 'flex' : 'none';
  });
  close.addEventListener('click', () => win.style.display = 'none');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const query = input.value.trim();
    if (!query) return;

    const userBubble = document.createElement('div');
    userBubble.style.cssText = 'background: #1d2128; color: #fff; padding: 10px 14px; border-radius: 10px 10px 2px 10px; max-width: 80%; align-self: flex-end;';
    userBubble.textContent = query;
    msgs.appendChild(userBubble);
    input.value = '';
    msgs.scrollTop = msgs.scrollHeight;

    try {
      const res = await fetch('{$apiEndpoint}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: query })
      });
      const data = await res.json();
      const botBubble = document.createElement('div');
      botBubble.style.cssText = 'background: #e2e8f0; color: #1e293b; padding: 10px 14px; border-radius: 10px 10px 10px 2px; max-width: 80%;';
      botBubble.textContent = data.reply || data.response || 'Sin respuesta del asistente.';
      msgs.appendChild(botBubble);
      msgs.scrollTop = msgs.scrollHeight;
    } catch(err) {
      console.error('Chat error', err);
    }
  });
})();
</script>
HTML;
    }
}
