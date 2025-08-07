// Selección de elementos
const userInput = document.getElementById("user-input");
const imageInput = document.getElementById("image-input");
const chatMessages = document.getElementById("chat-messages");

const API_BASE = "http://localhost:8000";

async function sendMessage() {
  const text = userInput.value.trim();
  const image = imageInput.files[0];

  if (!text && !image) {
    alert("Por favor escribí un mensaje o subí una imagen.");
    return;
  }

  if (text) appendMessage("Tú", text, "user");
  if (image) appendMessage("Tú", `📷 Imagen subida: ${image.name}`, "user");

  try {
    const form = new FormData();
    if (text) form.append("texto", text);
    if (image) form.append("imagen", image);

    const resp = await fetch(`${API_BASE}/api/reclamos`, {
      method: "POST",
      body: form,
    });

    if (!resp.ok) {
      const err = await resp.json().catch(() => ({}));
      throw new Error(err.error || "Error al enviar el reclamo");
    }

    const data = await resp.json();
    const respuesta = data.categoria
      ? `Entendido. Categoría: ${data.categoria}.`
      : "Gracias por tu reclamo. Lo estamos procesando.";
    appendMessage("Bot", respuesta, "bot");
  } catch (e) {
    appendMessage("Bot", `Error: ${e.message}`, "bot");
  }

  userInput.value = "";
  imageInput.value = "";
}

function appendMessage(sender, message, type) {
  const msgDiv = document.createElement("div");
  msgDiv.classList.add("message", type);
  msgDiv.innerHTML = `<strong>${sender}:</strong> ${message}`;
  chatMessages.appendChild(msgDiv);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function loadHistory() {
  try {
    const resp = await fetch(`${API_BASE}/api/reclamos`);
    if (!resp.ok) return;
    const { data } = await resp.json();
    (data || []).reverse().forEach((r) => {
      if (r.texto) appendMessage("Tú", r.texto, "user");
      if (r.imagen) appendMessage("Tú", `📷 Imagen: ${r.imagen}`, "user");
      appendMessage("Bot", `Categoría registrada: ${r.categoria}`, "bot");
    });
  } catch (_) {}
}

window.addEventListener("DOMContentLoaded", () => {
  loadHistory();
  userInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") sendMessage();
  });
});
