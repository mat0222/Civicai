// Selección de elementos
const userInput = document.getElementById("user-input");
const imageInput = document.getElementById("image-input");
const chatMessages = document.getElementById("chat-messages");

// Función principal
function sendMessage() {
    const text = userInput.value.trim();
    const image = imageInput.files[0];

    // Validación
    if (!text && !image) {
        alert("Por favor escribí un mensaje o subí una imagen.");
        return;
    }

    // Mostrar mensaje del usuario
    if (text) {
        appendMessage("Tú", text, "user");
    }

    // Mostrar nombre de imagen si hay
    if (image) {
        appendMessage("Tú", `📷 Imagen subida: ${image.name}`, "user");
    }

    // Simular respuesta del bot
    setTimeout(() => {
        const respuesta = generarRespuesta(text);
        appendMessage("Bot", respuesta, "bot");
    }, 1000);

    // Limpiar inputs
    userInput.value = "";
    imageInput.value = "";
}

// Agrega mensaje al chat
function appendMessage(sender, message, type) {
    const msgDiv = document.createElement("div");
    msgDiv.classList.add("message", type);
    msgDiv.innerHTML = `<strong>${sender}:</strong> ${message}`;
    chatMessages.appendChild(msgDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Simulación de categorización simple (se reemplaza con IA después)
function generarRespuesta(texto) {
    if (!texto) return "Gracias por subir la imagen. Estamos analizando el problema.";

    const lower = texto.toLowerCase();

    if (lower.includes("bache")) return "Entendido. Categoría: Bache.";
    if (lower.includes("luz") || lower.includes("alumbrado")) return "Entendido. Categoría: Alumbrado público.";
    if (lower.includes("basura")) return "Entendido. Categoría: Basura acumulada.";
    if (lower.includes("agua")) return "Entendido. Categoría: Pérdida de agua.";

    return "Gracias por tu reclamo. Lo estamos procesando.";
}
