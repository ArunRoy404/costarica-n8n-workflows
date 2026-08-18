import fs from 'fs';
import path from 'path';
import axios from 'axios';
import dotenv from 'dotenv';

dotenv.config();

const API_URL = process.env.N8N_API_URL;
const API_KEY = process.env.N8N_API_KEY;

if (!API_URL || !API_KEY) {
  console.error('Error: N8N_API_URL and N8N_API_KEY must be defined in your .env file.');
  process.exit(1);
}

// Clean up slash at the end of API_URL if present
const baseUrl = API_URL.endsWith('/') ? API_URL.slice(0, -1) : API_URL;
const workflowsDir = path.join(process.cwd(), 'workflows');

function sanitizeFilename(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9-_]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '');
}

async function pullWorkflows() {
  try {
    if (!fs.existsSync(workflowsDir)) {
      fs.mkdirSync(workflowsDir, { recursive: true });
    }

    console.log(`Connecting to n8n instance at: ${baseUrl}`);
    
    let workflows = [];
    let nextCursor = null;
    let hasMore = true;

    // 1. Fetch all workflow metadata (paginated)
    while (hasMore) {
      const url = `${baseUrl}/workflows`;
      const params = {};
      if (nextCursor) {
        params.cursor = nextCursor;
      }

      const response = await axios.get(url, {
        headers: {
          'X-N8N-API-KEY': API_KEY,
        },
        params,
      });

      const { data, nextCursor: newCursor } = response.data;

      if (data && Array.isArray(data)) {
        // Only keep workflows that are not archived
        const nonArchivedWorkflows = data.filter(flow => flow.isArchived === false);
        workflows = workflows.concat(nonArchivedWorkflows);
      }

      if (newCursor) {
        nextCursor = newCursor;
      } else {
        hasMore = false;
      }
    }

    console.log(`Found ${workflows.length} workflows. Fetching details...`);

    // 2. Fetch details for each workflow and save it locally
    for (const flowMetadata of workflows) {
      console.log(`Pulling workflow: "${flowMetadata.name}" (ID: ${flowMetadata.id})...`);
      
      const detailUrl = `${baseUrl}/workflows/${flowMetadata.id}`;
      const detailResponse = await axios.get(detailUrl, {
        headers: {
          'X-N8N-API-KEY': API_KEY,
        },
      });

      const fullWorkflow = detailResponse.data;
      
      // Clean metadata fields we do not want to track or that could cause issues
      // n8n api PUT/POST accepts nodes, connections, name, active, settings, staticData, etc.
      // We will keep ID in the file so we can update it on push
      const sanitizedName = sanitizeFilename(fullWorkflow.name || 'untitled');
      const filename = `${fullWorkflow.id}_${sanitizedName}.json`;
      const filePath = path.join(workflowsDir, filename);

      // Pretty print JSON
      fs.writeFileSync(filePath, JSON.stringify(fullWorkflow, null, 2), 'utf-8');
      console.log(`  Saved to: workflows/${filename}`);
    }

    console.log('\nSuccess! All workflows successfully pulled to the local "workflows/" directory.');
  } catch (error) {
    console.error('Error pulling workflows:', error.response?.data || error.message);
    process.exit(1);
  }
}

pullWorkflows();
