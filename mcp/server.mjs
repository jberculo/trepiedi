#!/usr/bin/env node
/**
 * MCP-server voor de Trepiedi-voetbalpoule.
 *
 * Tools:
 *   - get_standings(poolCode?)          stand van een poule (default: standaardpoule)
 *   - list_matches()                    alle wedstrijden + of ze open staan
 *   - set_match_result(...)             uitslag van een open wedstrijd zetten/aanpassen
 *
 * Config via environment variables:
 *   TREPIEDI_API_URL   basis-URL (default https://trepiedi.online)
 *   TREPIEDI_API_KEY   persoonlijke beheerders-API-sleutel (alleen nodig om uitslagen te schrijven)
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

const BASE_URL = (process.env.TREPIEDI_API_URL || 'https://trepiedi.online').replace(/\/$/, '');
const API_KEY = process.env.TREPIEDI_API_KEY || '';

/** Doet een API-call en geeft een MCP-tekstresultaat terug. */
async function call(method, path, { body, useKey = false } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (useKey) {
        if (!API_KEY) {
            return toolError('Geen API-sleutel ingesteld. Zet TREPIEDI_API_KEY om uitslagen te kunnen aanpassen.');
        }
        headers['X-API-Key'] = API_KEY;
    }

    let res;
    try {
        res = await fetch(`${BASE_URL}${path}`, {
            method,
            headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch (e) {
        return toolError(`Kon de API niet bereiken: ${e.message}`);
    }

    const text = await res.text();
    if (!res.ok) {
        return toolError(`API-fout (HTTP ${res.status}): ${text}`);
    }
    return { content: [{ type: 'text', text }] };
}

function toolError(message) {
    return { isError: true, content: [{ type: 'text', text: message }] };
}

const server = new McpServer({ name: 'trepiedi', version: '1.0.0' });

server.registerTool(
    'get_standings',
    {
        title: 'Stand opvragen',
        description: 'Huidige stand van een poule. Zonder poole-code de standaardpoule.',
        inputSchema: { poolCode: z.string().optional().describe('Inschrijfcode van de poule (optioneel)') },
    },
    async ({ poolCode }) => {
        const path = poolCode ? `/api/standings/${encodeURIComponent(poolCode)}` : '/api/standings';
        return call('GET', path);
    },
);

server.registerTool(
    'list_matches',
    {
        title: 'Wedstrijden opvragen',
        description: 'Alle wedstrijden met uitslag en of ze nog open staan (incl. id om een uitslag te zetten).',
        inputSchema: {},
    },
    async () => call('GET', '/api/matches'),
);

server.registerTool(
    'set_match_result',
    {
        title: 'Uitslag zetten',
        description: 'Voegt de uitslag toe of past die aan van een wedstrijd die nog open staat (alleen met een beheerders-API-sleutel).',
        inputSchema: {
            matchId: z.number().int().positive().describe('Id van de wedstrijd (zie list_matches)'),
            homeScore: z.number().int().nonnegative().describe('Doelpunten thuisploeg'),
            awayScore: z.number().int().nonnegative().describe('Doelpunten uitploeg'),
            advancingSide: z.enum(['home', 'away']).optional().describe('Welke kant gaat door (verplicht bij finished=true)'),
            finished: z.boolean().optional().describe('Uitslag definitief maken (telt mee voor de punten)'),
        },
    },
    async ({ matchId, homeScore, awayScore, advancingSide, finished }) => {
        const body = { homeScore, awayScore };
        if (advancingSide !== undefined) body.advancingSide = advancingSide;
        if (finished !== undefined) body.finished = finished;
        return call('POST', `/api/matches/${matchId}/result`, { body, useKey: true });
    },
);

await server.connect(new StdioServerTransport());
