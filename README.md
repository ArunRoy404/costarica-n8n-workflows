# n8n Workflow Local Development & Git Synchronization

This project is set up to allow you to develop, version-control, and sync your remote n8n workflows locally using **Antigravity**.

By storing workflows as JSON files in this directory, Antigravity can analyze, edit, and create new workflows or subflows, and you can push them back to your self-hosted n8n instance instantly.

---

## 🛠️ Getting Started

### 1. Setup Environment Configuration
Create a `.env` file in the root of this project:
```bash
cp .env.example .env
```

Open `.env` and fill in your details:
- **`N8N_API_URL`**: The base REST API URL of your remote n8n instance (e.g., `https://n8n.yourdomain.com/api/v1`).
- **`N8N_API_KEY`**: Your owner-level API Key. You can generate one from the **Settings > Owner settings** or **API** section in your remote n8n UI.

### 2. Install Dependencies
Run the following command to install the required scripts packages:
```bash
npm install
```

---

## 🚀 How to Synchronize Workflows

### Pull Workflows (Remote ➡️ Local)
To download all workflows and subflows from your remote n8n instance into the `workflows/` folder:
```bash
npm run pull
```
- This script fetches all workflows, format-prints them into readable JSON files, and names them using their ID and display name (e.g., `workflows/1_send_daily_reports.json`).

### Push Workflows (Local ➡️ Remote)
To upload your local changes back to the remote instance:
```bash
npm run push
```
- If the workflow has an existing `id` that matches one on the server, it will **update** it.
- If you created a new `.json` file without an `id` (or if it doesn't exist on the server), it will **create** it, assign a remote ID, and rename the local file with the remote ID prefix.

---

## 🤖 Developing with Antigravity

Since all workflows are stored as readable JSON files under `workflows/`, you can use Antigravity to:
- **Analyze Flows**: Ask Antigravity to explain a workflow or identify potential bugs.
- **Modify Nodes/Parameters**: Tell Antigravity to update specific node configs, e.g.:
  > "In workflow `1_send_daily_reports.json`, change the HTTP request URL to `https://api.newsource.com`."
- **Inject Custom Code**: Ask Antigravity to write JavaScript or Python scripts for your n8n **Code Nodes**, and inject them directly into the workflow JSON.
- **Create New Workflows**: Ask Antigravity to build a new workflow from scratch, save it in `workflows/`, and run `npm run push` to deploy it.
