import fs from 'fs';
import path from 'path';
import vm from 'vm';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const dir = path.join(__dirname, '..', 'workflows');
const files = fs.readdirSync(dir).filter(f => f.endsWith('.json'));

const allWorkflowIds = new Map();
const allWorkflowData = [];

console.log('🔍 Starting Comprehensive Audit on ' + files.length + ' Workflows...\n');

let totalErrors = 0;
let totalWarnings = 0;

// First pass: collect IDs and parse
for (const file of files) {
  const filePath = path.join(dir, file);
  try {
    const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    allWorkflowIds.set(data.id, { file, name: data.name });
    allWorkflowData.push({ file, data });
  } catch (err) {
    console.error(`❌ FATAL: Invalid JSON in ${file}: ${err.message}`);
    totalErrors++;
  }
}

// Second pass: deep validation
for (const { file, data } of allWorkflowData) {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`📂 ${file}`);
  console.log(`   ID: ${data.id} | Name: ${data.name} | Nodes: ${data.nodes ? data.nodes.length : 0}`);

  const nodeMap = new Map();
  const nodeNames = new Set();
  const nodes = data.nodes || [];
  const connections = data.connections || {};

  // 1. Check Node uniqueness & Code Syntax
  nodes.forEach(node => {
    if (nodeNames.has(node.name)) {
      console.error(`   ❌ DUPLICATE NODE NAME: "${node.name}"`);
      totalErrors++;
    }
    nodeNames.add(node.name);
    nodeMap.set(node.name, node);

    // Validate Code Node JS syntax
    if (node.parameters && node.parameters.jsCode) {
      try {
        new vm.Script(`(function() {\n${node.parameters.jsCode}\n})`);
      } catch (jsErr) {
        console.error(`   ❌ JS SYNTAX ERROR in node "${node.name}": ${jsErr.message}`);
        totalErrors++;
      }
    }

    // Validate Sub-Workflow references
    if (node.type.includes('executeWorkflow') || node.type.includes('toolWorkflow')) {
      const targetId = node.parameters?.workflowId?.value || node.parameters?.workflowId;
      if (targetId && !allWorkflowIds.has(targetId)) {
        console.warn(`   ⚠️ UNKNOWN SUBWORKFLOW ID in "${node.name}": ${targetId}`);
        totalWarnings++;
      } else if (targetId) {
        console.log(`   🔗 Subflow Link: "${node.name}" -> ${allWorkflowIds.get(targetId).name} (${targetId})`);
      }
    }
  });

  // 2. Validate Connection Graph
  for (const [sourceNodeName, targets] of Object.entries(connections)) {
    if (!nodeMap.has(sourceNodeName)) {
      console.error(`   ❌ CONNECTION SOURCE NOT FOUND: "${sourceNodeName}"`);
      totalErrors++;
      continue;
    }

    if (targets.main) {
      targets.main.forEach((outputBranch, branchIndex) => {
        if (!Array.isArray(outputBranch)) return;
        outputBranch.forEach(conn => {
          if (!nodeMap.has(conn.node)) {
            console.error(`   ❌ CONNECTION TARGET NOT FOUND: "${conn.node}" from "${sourceNodeName}" [branch ${branchIndex}]`);
            totalErrors++;
          }
        });
      });
    }
  }

  // 3. Check for IF nodes with dangling / unconnected branches
  nodes.filter(n => n.type.includes('if')).forEach(ifNode => {
    const ifConns = connections[ifNode.name]?.main || [];
    const trueBranch = ifConns[0] || [];
    const falseBranch = ifConns[1] || [];

    if (trueBranch.length === 0) {
      console.warn(`   ⚠️ IF Node "${ifNode.name}" has NO connection on TRUE branch (index 0)`);
      totalWarnings++;
    }
    if (falseBranch.length === 0) {
      console.log(`   ℹ️ IF Node "${ifNode.name}" has no connection on FALSE branch (index 1)`);
    }
  });

  // 4. Check Google Sheets configuration
  nodes.filter(n => n.type.includes('googleSheets')).forEach(sheetNode => {
    const op = sheetNode.parameters?.operation || 'read';
    const docId = sheetNode.parameters?.documentId?.value || sheetNode.parameters?.documentId;
    const sheetId = sheetNode.parameters?.sheetName?.value || sheetNode.parameters?.sheetName;
    console.log(`   📊 Sheet Node "${sheetNode.name}": op=${op}, doc=${docId ? 'OK' : 'MISSING'}, sheet=${sheetId}`);
  });
}

console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('📊 AUDIT SUMMARY:');
console.log(`   Total Errors: ${totalErrors}`);
console.log(`   Total Warnings: ${totalWarnings}`);
if (totalErrors === 0) {
  console.log('🎉 ALL WORKFLOWS PASSED VALIDATION CHECKS!');
} else {
  console.log('🚨 PLEASE FIX ERRORS BEFORE PUSHING!');
}
