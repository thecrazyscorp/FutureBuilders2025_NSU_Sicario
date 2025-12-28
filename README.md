# FutureBuilders2025_NSU_Sicario

<h2>Team name: NSU_Sicario</h2>
<h2>Members: </h2>
1. Avradip Dutta Charu
2. Farhana Rahman
3. Faheema Shaheed Tamanna

<h2>Problem Statement: </h2>

<h3>The Silent Struggle: Why Medical Support in Bangladesh's Hill Tracts and Rural Regions Remains Hard to find </h3>

In the sprawling greens of Bangladesh's hill tracts and the distant stretches of rural villages, life often moves with a quiet rhythm-yet behind that calm lies a persistent struggle: access to medical support. For millions living in these regions, healthcare is not a guaranteed right but a long-distance hope, often travelling on unpaved roads, across rivers, or through steep, forested terrain.

<h2>Solution Overview: </h2>

In Bangladesh’s hill tracts and rural villages, access to healthcare is often limited by difficult terrain, long travel distances, and unreliable internet connectivity. To address this challenge, we built a lightweight web-based healthcare triage and referral system using basic HTML and CSS, a PHP backend, and a MySQL database, ensuring accessibility on low-end devices and slow networks.

The system integrates a locally running AI health assistant to support early assessment and case prioritization. Instead of relying on cloud-based or paid APIs, all AI components run locally, making the solution cost-effective, privacy-friendly, and reliable in low-resource environments.

Patients submit symptoms through text or voice input. The AI assistant generates a clear, non-diagnostic summary, assigns an urgency level (Green / Yellow / Red), and routes the case to an appropriate doctor. Doctors then review the case, access the patient’s medical history, and send diagnoses or recommendations back to the patient asynchronously.

The AI acts strictly as a support tool, helping doctors prioritize and understand cases faster, while all medical decisions remain with licensed professionals.

We use local AI tools to avoid dependency on external services:

<li>
  <item> Whisper (local, Python): Converts patient audio input into English text </item>
  <item> Ollama + LLaMA2-7B: Generates calm, human-readable medical explanations (no diagnosis, no prescriptions)</item>
  <item>Rule-based logic (PHP): Assigns severity (GREEN / YELLOW / RED) and suggests doctor specialty based on keywords</item>
</li>

<h2>Technologies Used:</h2>
Frontend: HTML, CSS, JavaScript
Backend: PHP
Database: MySQL

<h2>AI tools disclosure: </h2>
1. ChatGPT
2. Claude AI

<h2> Handling Limited Internet Access: </h2>
To ensure usability in rural and remote areas with unstable connectivity, the system is intentionally designed to be lightweight and resilient. The user interface is built using basic HTML and CSS with minimal JavaScript, allowing pages to load quickly even on slow networks and low-end devices.

To prevent data loss during network interruptions, the application includes an offline submission mechanism. When internet connectivity is unavailable, patient case data is stored locally in the browser. Once the connection is restored, the system automatically submits the stored case for doctor review. This ensures that patients can still report symptoms without needing continuous internet access.

This approach makes the system reliable and practical for real-world use in low-resource rural settings.
