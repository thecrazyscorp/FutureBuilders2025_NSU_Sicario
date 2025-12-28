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
<img width="1920" height="1080" alt="601331056_900450112554674_3001598655329220307_n" src="https://github.com/user-attachments/assets/11b14f72-2e07-49ec-97ba-bf6b16f1013c" />
<img width="1920" height="1080" alt="607388757_1414327960257828_8909749231302933761_n" src="https://github.com/user-attachments/assets/211d60e0-460c-4763-8b7b-632388979252" />
<img width="1920" height="1080" alt="608013260_1195191595922647_5670364376952844700_n (1)" src="https://github.com/user-attachments/assets/fb70dce2-0c8b-46f9-b1b5-dc46285022ca" />
<img width="1920" height="1080" alt="605209400_898985252562582_1583804922678032380_n (1)" src="https://github.com/user-attachments/assets/d76c0f2a-e4e0-401d-93c9-4fae9807a269" />
<img width="1920" height="1080" alt="608013260_1195191595922647_5670364376952844700_n" src="https://github.com/user-attachments/assets/216d4fe8-e488-4ecb-b0ee-767ad4656d49" />
<img width="1920" height="1080" alt="605209400_898985252562582_1583804922678032380_n" src="https://github.com/user-attachments/assets/a9830248-457b-410f-beff-f10fc86852e7" />
<img width="2048" height="1158" alt="605444166_1417214983344810_2743742666305606620_n" src="https://github.com/user-attachments/assets/9b9c993d-360e-4b27-92f5-b40cb20a8d80" />
<img width="2048" height="1161" alt="606801023_1519227599341009_5064187568355049498_n" src="https://github.com/user-attachments/assets/659d6715-4617-4b0a-b86b-4da455b815e3" />
<img width="2048" height="1178" alt="599921341_865283326202219_3585983543988682064_n" src="https://github.com/user-attachments/assets/27909e2d-557b-47a8-b5e0-f1d0ac5bfdd2" />


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
