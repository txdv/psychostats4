/**
 * =============================================================================
 * SourceMod PsychoStats Plugin
 * Implements support for PsychoStats and enhances game logging to provide more
 * statistics. 
 *
 * This plugin will add "Spatial" stats to mods (just like TF). This allows
 * Heatmaps and trajectories to be created and viewed in the player stats.
 * This plugin will also 'fix' the game logging so the first map to run on 
 * server restart will log properly (HLDS doesn't log the first map). This
 * will prevent any 'unknown' maps from appearing in your player stats.
 *
 * =============================================================================
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, version 3.0, as published by the
 * Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Version: $Id$
 * Author:  Stormtrooper <http://www.psychostats.com/>
 */

#pragma semicolon 1

#include <sourcemod>
#include <logging>
#include <sdktools>

// If true, the initial map that is loaded will be properly logged so that any stats application
// can accurately determine what the first map played was. This fixes a bug in the HLDS server.
new bool:logMap = true;

// If true, the spatial stats for player deaths will be recorded. This is automatically disabled
// for Team Fortress servers since TF includes this info with its standard kill event.
new bool:logSpatial = true;


public Plugin:myinfo = 
{
	name = "PsychoStats Plugin",
	author = "Stormtrooper",
	description = "PsychoStats Spatial Plugin",
	version = "1.0",
	url = "http://www.psychostats.com/"
};

new bool:ignoreKill = true;
new String:gameFolder[64];

public OnPluginStart()
{
	GetGameFolderName(gameFolder, sizeof(gameFolder));
	logSpatial = !(StrEqual(gameFolder, "tf"));

	if (logSpatial) {
		HookEvent("player_death", Event_PlayerDeath);
		AddGameLogHook(LogEvent);
	}

	if (logMap) {
		AddGameLogHook(LogMapEvent);
	}
}


// write a "Loading map" event in order to fix a problem with the HLDS logging.
// This will prevent an "unknown" map from appearing in your player stats.
public Action:LogMapEvent(const String:message[]) {
	// The "Log file started" message is not captured by sourcemod (I assume it's an engine event; not a mod event)
	// So I have to simply trigger on the very first message received, 
	// which will be the first event AFTER "Log file started" (and is usually a player event; like 'player connected')

        decl String:map[128];
	GetCurrentMap(map, sizeof(map));
	LogToGame("Loading map \"%s\" (psychostats)", map);

	// only record the first map, after that remove our hook.
	RemoveGameLogHook(LogMapEvent);
	return Plugin_Continue;
}

// grab all log events as they are written to the game logs ...
public Action:LogEvent(const String:message[]) {
	// lookout for "killed" and "committed suicide" events
	// This is not the desired way to do this, but I can't find another way to more accurately do it
	// We can't stop a log event from logging within the log event itself, so we have to override it here.
	if (StrContains(message, ">\" killed \"") > 0 || StrContains(message, "\" committed suicide with \"")) {
		if (ignoreKill) {
			// do not log the current event
			// Event_PlayerDeath will trigger next and log a 'killed' event instead
			return Plugin_Handled;
		} else {
			// ignore the next kill event that comes in
			ignoreKill = true;
		}
	}

//	LogToGame("// PSYCHOSTATS // %s", message);
	return Plugin_Continue;
}

public Action:Event_PlayerDeath(Handle:event, const String:name[], bool:dontBroadcast)
{
	ignoreKill = false;

	/* Get player IDs */
        new victimId = GetEventInt(event, "userid");
        new attackerId = GetEventInt(event, "attacker");
	new bool:suicide = false;

	/* Break extra logging if suicide */
	if(victimId == attackerId)
	{
		suicide = true;
//		return Plugin_Continue;
	}

	/* Get both players' location coordinates */
        new Float:victimLocation[3];
        new Float:attackerLocation[3];
        new victim = GetClientOfUserId(victimId);
        new attacker = GetClientOfUserId(attackerId);
        GetClientAbsOrigin(victim, victimLocation);
        GetClientAbsOrigin(attacker, attackerLocation);

	/* Get weapon */
        decl String:weapon[64];
        GetEventString(event, "weapon", weapon, sizeof(weapon));

	/* Is headshot? */
        new bool:headshot = GetEventBool(event, "headshot");

	/* Get both players' name */
	decl String:attackerName[64];
	decl String:victimName[64];
	GetClientName(attacker, attackerName, sizeof(attackerName));
	GetClientName(victim, victimName, sizeof(victimName));

	/* Get both players' SteamIDs */
	decl String:attackerSteamId[64];
	decl String:victimSteamId[64];
	GetClientAuthString(attacker, attackerSteamId, sizeof(attackerSteamId));
	GetClientAuthString(victim, victimSteamId, sizeof(victimSteamId));

	/* Get both players' teams */
	decl String:attackerTeam[64];
	decl String:victimTeam[64];
	GetTeamName(GetClientTeam(attacker), attackerTeam, sizeof(attackerTeam));
	GetTeamName(GetClientTeam(victim), victimTeam, sizeof(victimTeam));

	if (suicide) {
	       	LogToGame("\"%s<%d><%s><%s>\" committed suicide with \"%s\" (attacker_position \"%d %d %d\")", 
	 		victimName,
	 		victimId,
	 		victimSteamId,
	 		victimTeam,
	 		weapon,
	       		RoundFloat(attackerLocation[0]),
	       		RoundFloat(attackerLocation[1]),
	       		RoundFloat(attackerLocation[2])
		);
	} else {
	       	LogToGame("\"%s<%d><%s><%s>\" killed \"%s<%d><%s><%s>\" with \"%s\" %s(attacker_position \"%d %d %d\") (victim_position \"%d %d %d\")", 
			attackerName,
			attackerId,
	 		attackerSteamId,
	 		attackerTeam,
	 		victimName,
	 		victimId,
	 		victimSteamId,
	 		victimTeam,
	 		weapon,
			((headshot) ? "(headshot) " : ""),
	 		RoundFloat(attackerLocation[0]),
	       		RoundFloat(attackerLocation[1]),
	       		RoundFloat(attackerLocation[2]),
	       		RoundFloat(victimLocation[0]),
	       		RoundFloat(victimLocation[1]),
	       		RoundFloat(victimLocation[2])
		);
	}
	return Plugin_Continue;
}
