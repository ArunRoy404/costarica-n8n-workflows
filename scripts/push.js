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

const baseUrl = API_URL.endsWith('/') ? API_URL.slice(0, -1) : API_URL;
const workflowsDir = path.join(process.cwd(), 'workflows');

function sanitizeFilename(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9-_]/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '');
}

async function pushWorkflows() {
  try {
    if (!fs.existsSync(workflowsDir)) {
      console.log('No "workflows/" directory found. Please run "npm run pull" first, or create workflows.');
      return;
    }

    const files = fs.readdirSync(workflowsDir).filter(f => f.endsWith('.json'));

    if (files.length === 0) {
      console.log('No workflow JSON files found in "workflows/".');
      return;
    }

    const targetId = process.argv[2];
    if (targetId) {
      console.log(`Targeting single workflow ID: ${targetId}`);
    }

    console.log(`Pushing workflows to remote n8n instance at: ${baseUrl}`);

    for (const filename of files) {
      const filePath = path.join(workflowsDir, filename);
      const fileContent = fs.readFileSync(filePath, 'utf-8');
      
      let workflow;
      try {
        workflow = JSON.parse(fileContent);
      } catch (err) {
        console.error(`Error parsing JSON in file ${filename}:`, err.message);
        continue;
      }

      if (targetId && workflow.id !== targetId) {
        continue;
      }

      const workflowName = workflow.name || 'Untitled Workflow';
      let remoteWorkflow = null;
      let isNew = !workflow.id;

      if (workflow.id) {
        try {
          console.log(`Updating workflow: "${workflowName}" (ID: ${workflow.id})...`);
          const url = `${baseUrl}/workflows/${workflow.id}`;
          
          // Use a strict whitelist of fields to avoid "read-only" or "additional properties" errors
          const updatePayload = {
            name: workflow.name,
            nodes: workflow.nodes,
            connections: workflow.connections,
            settings: workflow.settings,
            staticData: workflow.staticData,
            description: workflow.description || ""
          };

          const response = await axios.put(url, updatePayload, {
            headers: {
              'X-N8N-API-KEY': API_KEY,
              'Content-Type': 'application/json',
            },
          });
          remoteWorkflow = response.data;
          console.log(`  Successfully updated.`);
        } catch (error) {
          // If 404, we treat it as a new workflow (needs POST)
          if (error.response && error.response.status === 404) {
            console.log(`  Workflow ID ${workflow.id} not found on remote. Attempting to create it...`);
            isNew = true;
          } else {
            console.error(`  Failed to update workflow:`, error.response?.data || error.message);
            continue;
          }
        }
      }

      if (isNew) {
        try {
          console.log(`Creating new workflow: "${workflowName}"...`);
          const url = `${baseUrl}/workflows`;
          
          // Use a strict whitelist for creating workflows too
          const createPayload = {
            name: workflow.name,
            nodes: workflow.nodes,
            connections: workflow.connections,
            settings: workflow.settings,
            staticData: workflow.staticData,
            description: workflow.description || ""
          };

          const response = await axios.post(url, createPayload, {
            headers: {
              'X-N8N-API-KEY': API_KEY,
              'Content-Type': 'application/json',
            },
          });
          remoteWorkflow = response.data;
          console.log(`  Successfully created with new ID: ${remoteWorkflow.id}`);
        } catch (error) {
          console.error(`  Failed to create workflow:`, error.response?.data || error.message);
          continue;
        }
      }

      // If we got a response, make sure the local file matches the remote ID and metadata
      if (remoteWorkflow && remoteWorkflow.id) {
        const sanitizedName = sanitizeFilename(remoteWorkflow.name || 'untitled');
        const expectedFilename = `${remoteWorkflow.id}_${sanitizedName}.json`;
        const expectedFilePath = path.join(workflowsDir, expectedFilename);

        // Update local file content with the full remote object (has remote ID)
        fs.writeFileSync(expectedFilePath, JSON.stringify(remoteWorkflow, null, 2), 'utf-8');

        // Delete the old file if it had a different name (e.g. if it had a different ID or name)
        if (expectedFilename !== filename) {
          fs.unlinkSync(filePath);
          console.log(`  Renamed local file from ${filename} to ${expectedFilename}`);
        }
      }
    }

    console.log('\nSuccess! All workflows successfully synchronized to the remote instance.');
  } catch (error) {
    console.error('Error during push execution:', error.message);
    process.exit(1);
  }
}

pushWorkflows();
