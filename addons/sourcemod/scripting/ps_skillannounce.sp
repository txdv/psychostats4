new Handle:hDatabase = INVALID_HANDLE;
new Handle:hSkills = INVALID_HANDLE;

public OnPluginStart()
{
 LoadTranslations("ps_skillannounce.phrases");
 HookEvent("player_connect", Event_PlayerConnect)
 HookEvent("player_death", Event_PlayerDeath)
 HookEvent("player_info", Event_PlayerInfo)
 StartSQL()
 hSkills = CreateKeyValues("Skills");
}

public StartSQL()
{
	SQL_TConnect(GotDatabase, "psychostats");
}
 
public GotDatabase(Handle:owner, Handle:hndl, const String:error[], any:data)
{
	if (hndl == INVALID_HANDLE)
	{
		LogError("Database failure: %s", error);
	} else {
		hDatabase = hndl;
	}
}

public Action:Event_PlayerInfo(Handle:event, const String:name[], bool:dontBroadcast)
{
 new String:plrName[64]
 GetEventString(event, "name", plrName, sizeof(plrName))
 KvSetFloat(hSkills, plrName, 50.0)
 PrintToServer("Setting name skill for %s", plrName)
 return Plugin_Continue
}

public Action:Event_PlayerConnect(Handle:event, const String:name[], bool:dontBroadcast)
{
 new String:plrName[64]
 GetEventString(event, "name", plrName, sizeof(plrName))
 KvSetFloat(hSkills, plrName, 50.0)
 GetSkill(plrName)
 return Plugin_Continue
}

public GetSkill(const String:plrName[])
{
	decl String:query[255]
	Format(query, sizeof(query), "SELECT skill, '%s' AS plrName FROM ps_plr WHERE uniqueid LIKE '%s'", plrName, plrName);
	SQL_TQuery(hDatabase, T_GetSkill, query)
}
 
public T_GetSkill(Handle:owner, Handle:query, const String:error[], any:data)
{
	new Float:plrSkill = 50.0
	new String:plrName[64]

	if (query == INVALID_HANDLE)
	{
		LogError("Query failed! %s", error)
	} else if (SQL_GetRowCount(query)) {
		SQL_FetchRow(query)
		plrSkill = SQL_FetchFloat(query, 0)
		SQL_FetchString(query, 1, plrName, sizeof(plrName))
	}
	KvSetFloat(hSkills, plrName, plrSkill)
	if(strlen(plrName) != 0 || plrSkill != 50.0)
	{
		AnnounceJoin(plrName, plrSkill)
	}
}

public AnnounceJoin(const String:plrName[], const Float:skill)
{
 PrintHintTextToAll("%t", "Joined", plrName, skill) 
 PrintToServer("%t", "Joined", plrName, skill) 
}

public Action:Event_PlayerDeath(Handle:event, const String:name[], bool:dontBroadcast)
{
 new victim_id = GetEventInt(event, "userid")
 new attacker_id = GetEventInt(event, "attacker")
 
 new victim = GetClientOfUserId(victim_id)
 new attacker = GetClientOfUserId(attacker_id)

 /* Get both players' name */
 new String:kname[64]
 new String:vname[64]
 GetClientName(attacker, kname, sizeof(kname))
 GetClientName(victim, vname, sizeof(vname))

 // Get current skills from KV table
 new Float:vskill = KvGetFloat(hSkills, vname, 50.0)
 new Float:kskill = KvGetFloat(hSkills, kname, 50.0)

 new Float:kbonus = 1.0
 new Float:vbonus = 1.0

 if (kskill > vskill) {
  // killer is better than the victim
  kbonus = Pow((kskill + vskill),2.0) / Pow(kskill,2.0);
  vbonus = kbonus * vskill / (vskill + kskill);
 } else {
  // the victim is better than the killer
  kbonus = Pow((vskill + kskill),2.0) / Pow(vskill,2.0) * vskill / kskill;
  vbonus = kbonus * (vskill + 50) / (vskill + kskill);
 }

 if (kbonus > 10.0) kbonus = 10.0 
 if (vbonus > 10.0) vbonus = 10.0

 if (kbonus > kskill) kbonus = kskill
 if (vbonus > vskill) vbonus = 1.0

 KvSetFloat(hSkills, kname, kskill+kbonus)
 KvSetFloat(hSkills, vname, vskill-vbonus)

 kskill = RoundToNearest(((kskill+kbonus) * 100)) / 100.0
 vskill = RoundToNearest(((vskill-vbonus) * 100)) / 100.0
 kbonus = RoundToNearest((kbonus * 100)) / 100.0
 vbonus = RoundToNearest((vbonus * 100)) / 100.0

 PrintToServer("%t", "Killer", kname, kbonus, kskill, vname, vbonus, vskill)
// PrintHintTextToAll("%s got %.2f points (%.2f) for killing %s who lost %.2f points (%.2f)", kname, kbonus, kskill, vname, vbonus, vskill)
// PrintToConsole(victim, "%s got %.2f points (%.2f) for killing %s who lost %.2f points (%.2f)", kname, kbonus, kskill, vname, vbonus, vskill)
// PrintToConsole(attacker, "%s got %.2f points (%.2f) for killing %s who lost %.2f points (%.2f)", kname, kbonus, kskill, vname, vbonus, vskill)
 PrintToConsole(victim, "%t", "Victim", kname, kbonus, kskill, vname, vbonus, vskill)
 PrintToConsole(attacker, "%t", "Killer", kname, kbonus, kskill, vname, vbonus, vskill)
 return Plugin_Continue
}