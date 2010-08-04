#
#	This file is part of PsychoStats.
#
#	Written by Jason Morriss <stormtrooper@psychostats.com>
#	Copyright 2008 Jason Morriss
#
#	PsychoStats is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	(at your option) any later version.
#
#	PsychoStats is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
#
#	$Id$
#
package PS::DBI;

use strict;
use warnings;
use base qw( PS::Core );

use PS::SourceFilter;
use DBI;
use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub new {
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $self = { };
	my $dbconf = ref $_[0] ? $_[0] : { @_ };

	$self->{$_} = $dbconf->{$_} foreach (qw(
		dbh
		dbtype dbhost dbport dbname dbuser dbpass
		dbtblprefix dbcompress
		fatal
	));

	# if true, any query that fails will die
	$self->{fatal} = 1 unless defined $self->{fatal};
	
	# sanitize connection paramaters
	$self->{dbtype} = 'mysql' unless defined $self->{dbtype};
	$self->{dbname} = 'psychostats' unless defined $self->{dbname};
	$self->{dbtblprefix} = '' unless defined $self->{dbtblprefix};
	$self->{dbcompress} = $self->{dbcompress} ? 1 : 0;

	# compile prefix
	$self->{dbtblcompiledprefix} = $self->{dbtblprefix} . "c_";

	# A hash of named prepared statement handles
	$self->{prepared} = {};

	$self->{totalwarnings} = 0;

	# Change the base class that we're creating
	$class .= "::" . $self->{dbtype};
	$self->{class} = $class;

	# load subclass
	eval "require $class";
	if ($@) {
		die("Database subclass '$class' has compile time errors:\n$@\n");
	}

	# An array of all tables used for psychostats
	$self->{tables} = [qw(
		awards awards_plrs
		clan clan_profile
		config config_awards config_clantags config_events
		config_logsources config_overlays
		config_plraliases config_plrbans config_plrbonuses
		errlog
		heatmaps
		geoip_cc geoip_ip
		match
		map map_data map_hourly map_statial
		plr plr_bans plr_chat plr_data
		plr_ids_ipaddr plr_ids_name plr_ids_guid
		plr_maps plr_profile plr_roles plr_sessions
		plr_victims plr_weapons
		plugins
		role role_data
		search_results
		sessions
		state themes user
		weapon weapon_data
	)];

	# An array of all 'compiled' tables
	$self->{compiled_tables} = [qw(
		plr_data plr_maps plr_roles plr_victims plr_weapons
		map_data role_data weapon_data
	)];

	# An array of all basic stats tables
	$self->{stats_tables} = [qw(
		plr_data plr_maps plr_roles plr_sessions plr_victims plr_weapons
		plr_chat plr_ids_guid plr_ids_ipaddr plr_ids_name
		map_data role_data weapon_data
	)];

	# An array of all special tables (tables that have extra
	# gametype_modtype tables)
	$self->{special_tables} = [qw(
		plr_data plr_maps plr_roles plr_sessions plr_victims plr_weapons
		map_data role_data weapon_data
	)];

	# An array of all PSlive tables
	$self->{live_tables} = [qw(
		live_entities live_events live_games
	)];

	bless($self, $class);

	my $code = '';
	
	# initialize the table names
	foreach (@{$self->{tables}}) {
		$self->{'t_' . $_} = $self->{dbtblprefix} . $_;
		#$code .= "sub t_$_ () { '$self->{dbtblprefix}$_' }\n" unless $self->can("t_$_");
	}

	# initialize compiled table names
	foreach (@{$self->{compiled_tables}}) {
		$self->{'c_' . $_} = $self->{dbtblcompiledprefix} . $_;
		#$code .= "sub c_$_ () { '$self->{dbtblprefix}c_$_' }\n" unless $self->can("c_$_");
	}

	# Create constant subs for each table name.
	# Note: Creating constant subs for table names turned out to be a bad
	# idea. It's much easier to reference tables by a hash element so they
	# can be interpolated into strings more easily.
	#if ($code) {
	#	#print $code;
	#	eval $code;
	#	if ($@) {
	#		$self->fatal_safe("Error creating table name subs!");
	#	}
	#}
	
	return $self->init;
}

# return a reference to a cloned copy of the object.
# if $args{prepared} is true (or if $args is a true scalar) then prepared
# statements will be copied over too.
sub clone {
	my $self = shift;
	my %args;
	if (ref $_[0]) {
		%args = (@_==1 ? ( prepared => $_[0]?1:0 ) : %{$_[0]});
	} else {
		$args{prepared} = $_[0] ? 1 : 0;
	}
	
	my $clone = {};
	foreach my $key (keys %$self) {
		next if $key eq 'prepared';
		next if $key eq 'sth';
		if (ref $self->{$key} eq 'HASH') {
			$clone->{$key} = { %{$self->{$key}} };
		} elsif (ref $self->{$key} eq 'ARRAY') {
			$clone->{$key} = [ @{$self->{$key}} ];
		} elsif (ref $self->{$key} eq 'DBI::db') {
			$clone->{$key} = $self->{$key}->clone;
		} else {
			# anything else is a normal scalar
			$clone->{$key} = $self->{$key};
		}
	}

	$clone->{lastcmd} = '';

	my $copy = bless($clone, ref $self);

	# copy over any prepared statements
	if ($args{prepared}) {
		foreach my $name (keys %{$self->{prepared}}) {
			my $st = $self->{prepared}{$name} || next;
			$copy->prepare($name, $st->{Statement});
		}
	}
	
	return $copy;
}

sub init { $_[0] }
sub init_database { $_[0] }

sub connect { undef } 
sub disconnect {
	my ($self) = @_;

	# cleanup any active prepared statement handles before we exit
	$self->{prepared}{$_}->finish for keys %{$self->{prepared}};
	$self->{prepared} = {};

	# cleanup internal statement handle if its active
	$self->{sth}->finish if $self->{sth};
	undef $self->{sth};

	# fully disconnect from the server, if the handle is active
	$self->{dbh}->disconnect if $self->{dbh};
	undef $self->{dbh};
}

# execute a named prepared statement
sub execute {
	my ($self, $name, @bind) = @_;

	# if the statement doesn't exist, do nothing and issue a warning
	if (!exists $self->{prepared}{$name}) {
		$self->{totalwarnings}++;
		carp("[WARNING] Attempt to execute unprepared statement '$name'");
		return;
	}
	
	$self->{lasterr} = '';
	my $ok = $self->{prepared}{$name}->execute(@bind);
	;;;$self->{lastcmd} = $self->{prepared}{$name}->{Statement};
	if (!$ok) {
		my $err = $self->err . ": " . $self->errstr;
		$self->{totalwarnings}++;
		$self->warn(
			"DB Query Error: $err" .
			"\n          SQL =" . $self->{prepared}{$name}{Statement} .
			"\n          BIND=" . join(', ', map { $self->{dbh}->quote($_) } @bind)
		);
		$self->{lasterr} = $err;
	}
	return $ok ? $self->{prepared}{$name} : undef;
}

# same as ->execute, but the command is printed as well (with all bind values)
sub execute_debug {
	my ($self, $name, @bind) = @_;
	my $ok = $self->execute($name, @bind);
	;;;$self->{lastcmd} = $self->{prepared}{$name}->{Statement};
	;;;if (exists $self->{prepared}{$name}) {
	;;;	my $q = $self->{prepared}{$name}{Statement};
	;;;	$q =~ s/\?/$_/e for map { $self->{dbh}->quote($_) } @bind;
	;;;	$self->debug1($q,0);
	;;;}
	return $ok;
}

# executes a prepared statement and fetches an array of hash rows
sub execute_fetchall {
	my ($self, $name, @bind) = @_;
	my $st = $self->execute($name, @bind) || return;
	my $rows = $st->fetchall_arrayref({});
	$st->finish;
	return wantarray ? @$rows : $rows;
}

# executes a prepared statement and fetches the first row only
sub execute_fetchrow {
	my ($self, $name, @bind) = @_;
	my $st = $self->execute($name, @bind) || return;
	my $row = $st->fetchrow_hashref;
	$st->finish;
	return wantarray ? %$row : $row;
}

# executes a prepared statement and fetches all columns into a single array
sub execute_selectall {
	my ($self, $name, @bind) = @_;
	my $st = $self->execute($name, @bind) || return;
	my @rows = map { @$_ } @{$st->fetchall_arrayref};
	$st->finish;
	return wantarray ? @rows : \@rows;
}

# executes a prepared statement and fetches the first column of the first row.
sub execute_selectcol {
	my ($self, $name, @bind) = @_;
	my $st = $self->execute($name, @bind) || return;
	my $row = $st->fetchrow_arrayref;
	$st->finish;
	return $row ? $row->[0] : undef;
}

# returns a previously prepared handle or undef if it doesn't exist
sub prepared {
	my ($self, $name) = @_;
	if (!exists $self->{prepared}{$name}) {
		#carp("[WARNING] Attempt to access an unprepared statement named '$name'");
		return;
	}
	return $self->{prepared}{$name};
}

# prepare a statement for later use and tag it with a name
sub prepare {
	my ($self, $name, $cmd, $force) = @_;
	my $named = defined $cmd;
	my $exists;
	
	# if $cmd is undefined then we're preparing an unnamed statement and we
	# won't be saving it for future use, instead the caller will use the
	# returned statement handle directly.
	$cmd = $name if !$named;
	$exists = $named ? (exists $self->{prepared}{$name} ? $self->{prepared}{$name} : undef) : undef;

	# if a statement exists already then finish it up, remove it and warn
	# that we're redefining a statement (unless force is true)
	if ($exists) {
		# If force wasn't specified then someone in the code is trying
		# to redefine a previously prepared statement unintentionally.
		# this is simply a warning to help avoid bugs in the future.
		if (!$force) {
			carp("[WARNING] Redefining prepared statement named \"$name\"");
		}

		# cleanly finish and remove the handle from memory
		$exists->finish;
		delete $self->{prepared}{$name};
	}

	# interpolate table names in query to real table names
	$cmd =~ s/\s([tc])_(\w+)/' ' . ($self->{$1.'_'.$2} || $1.'_'.$2)/ge;

	# prepare the statement and return a reference to it
	my $st = $self->{dbh}->prepare($cmd);
	$self->{prepared}{$name} = $st if $named;
	return $st;
}

# Returns a string that can be used in a statement to assign the max value. No
# qouting is performed to keep this simple and fast. So be sure $var and $val
# are proper.
sub expr_max {
	my ($self, $var, $val) = @_;
	return "IF($var>$val,$var,$val)";
}

# Opposite of expr_max
sub expr_min {
	my ($self, $var, $val) = @_;
	return "IF($var<$val,$var,$val)";
}

# returns an expression (with placeholders) for updating a table column. $bind
# is an optional arrayref that will have the $val added to it for binding
# later...
sub expr {
	my ($self, $key, $expr, $val, $bind) = @_;
	my $cmd .= "$key=";
	if ($expr eq '+') {
		$cmd .= $key . $expr . '?';
		push(@$bind, $val) if $bind;
	} elsif ($expr eq '>') {
		$cmd .= $self->expr_max($key, '?');
		push(@$bind, $val, $val) if $bind;
	} elsif ($expr eq '<') {
		$cmd .= $self->expr_min($key, '?');
		push(@$bind, $val, $val) if $bind;
	} elsif ($expr eq '=') {
		$cmd .= '?';
		push(@$bind, $val) if $bind;
	}
	return $cmd;
}

# returns the table name with the correct prefix on it
sub tbl { $_[0]->{dbtblprefix} . $_[1] }
sub ctbl { $_[0]->{dbtblcompiledprefix} . $_[1] }

# Create a table. This routine is only used within the context of the
# PsychoStats system. It's not meant as a generic CREATE TABLE routine to create
# tables outside of PS. note: indexes must be created with the separate
# "create_*_index" methods AFTER the table is created ->create(table, fields,
# order)
sub create {
	my $self = shift;
	my $tablename = shift;
	my $fields = shift;
	my $order = shift || [ sort keys %$fields ];
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my $cmd = $self->_create_header($tbl);

	my $i = 0;
	foreach my $key (@$order) {
		my $type = "_type_" . $fields->{$key};
		my $def  = "_default_" . $fields->{$key};
		$cmd .= "\t" . $self->$type($key);
		$cmd .= " " . $self->_attrib_null(0);
		$cmd .= " " . $self->$def;
		$cmd .= (++$i == @$order) ? "\n" : ",\n";
	}

	$cmd .= $self->_create_footer($tbl);
	
	$self->do($cmd); # || $self->fatal("Error creating table $tablename: " . $self->errstr);
}

sub _create_header 	{ "CREATE TABLE $_[1] (" }
sub _create_footer 	{ ")" }
sub _type_uint 		{ $_[0]->{dbh}->quote_identifier($_[1]) . " INT UNSIGNED" }
sub _type_int 		{ $_[0]->{dbh}->quote_identifier($_[1]) . " INT" }
sub _type_float  	{ $_[0]->{dbh}->quote_identifier($_[1]) . " FLOAT(10,2)" }
sub _type_date  	{ $_[0]->{dbh}->quote_identifier($_[1]) . " DATE" }
sub _default_uint  	{ my $v = $_[0]->{dbh}->quote($_[1] || 0); " DEFAULT $v" }
sub _default_int  	{ my $v = $_[0]->{dbh}->quote($_[1] || 0); " DEFAULT $v" }
sub _default_float  	{ my $v = $_[0]->{dbh}->quote($_[1] || 0.00); " DEFAULT $v" }
sub _default_date  	{ my $v = $_[0]->{dbh}->quote($_[1] || '0000-00-00'); " DEFAULT $v" }
sub _attrib_null 	{ $_[1] ? " NULL" : " NOT NULL" }

# ->create_primary_index(table, cols)
sub create_primary_index { }
sub create_unique_index { }
sub create_index { }

# ->alter_table(table, column cmd)
sub alter_table_add { }
sub alter_table_drop { }

# INTERNAL: sub-classes have to override this to provide a method to 'explain'
# the details of a table. The results are returned as a hash, or undef if the
# table doesn't exist.
sub _explain { $_[0]->fatal("Abstract method '_explain' called") }

# optimize a table. An array of table names can be given as well.
# ->optimize(table, table2, ...)
sub optimize {
	my $self = shift;
	my @tables = map { $self->{dbh}->quote_identifier( $_ ) } ref $_[0] ? @$_[0] : @_;
	$self->query("OPTIMIZE TABLE " . join(', ', @tables)) if @tables;
}

# ABSTRACT method to determine if a table already exists
# ->table_exists(table)
sub table_exists {
	my ($self, $table) = @_;
	$self->fatal("Abstract method called: $self->{class}::table_exists($table)");
}

# returns the column information of a table (EXPLAIN) as a hash
# ->table_info(table)
sub table_info {
	my $self = shift;
	my $tablename = shift;
	if (wantarray) {
		return $self->_explain($tablename);
	} else {
		return scalar $self->_explain($tablename);
	}
}

# Takes an arrayref and returns a WHERE clause that matches on each key=value
# pair in the array using AND or OR as the glue specified.
# Note: an array is used so the order of the matches remains intact (and it's
# faster than a hash) ->where([ key => value, key => value ], 'and' || 'or')
sub where {
	my $self = shift;
	my $matches = ref $_[0] eq 'ARRAY' ? shift : return $_[0];		# assume it's a string and return it
	my $andor = shift || 'AND';
	my $where = '';
	for (my $i=0; $i < @$matches; $i+=2) {
		my $key = $matches->[$i];
		my $has_op = $self->has_op($key);
		
		# don't quote the key if there's an operator on it
		$where .= $has_op ? $key : $self->{dbh}->quote_identifier($key);
		if ($has_op) {
			$where .= $self->{dbh}->quote($matches->[$i+1]) if defined $matches->[$i+1];
		} else {
			$where .= defined $matches->[$i+1]
				? '=' . $self->{dbh}->quote($matches->[$i+1])
				: ' IS NULL';
		}
		$where .= " $andor " if $i+2 < @$matches;
	}
	$where = '1' if $where eq '';			# match anything 
	return $where;
}

# returns true if the SQL fragment given has an operator, ie: "key !=" is true
sub has_op {
	(lc $_[1] =~ /[ <>!=]|is (?:not )?null|between/);
}

# Inserts a row into the table. 2nd parameter is a hash or array ref of (field
# => value) elements. Field names and values are automatically quoted before
# insertion. if $noquotes is true then the field keys and values are not quoted
# and are assumed to be properly quoted already. ->insert('table', { fields },
# $noquotes[0|1])
sub insert {
	my $self = shift;
	my $tbl = shift;
	my $fields = ref $_[0] ? shift : { @_ };
	my $noquotes = shift;
	my $dbh = $self->{dbh};
	my $res;

	if (ref $fields eq 'HASH') {
		my @keys = keys %$fields;
		if ($noquotes) {
			$res = $self->query("INSERT INTO $tbl (" . join(', ', @keys) . ") " . 
				"VALUES (" . join(', ', map { $fields->{$_} } @keys) . ")"
			);
		} else {
			$res = $self->query("INSERT INTO $tbl (" . join(', ', map { $dbh->quote_identifier($_) } @keys) . ") " . 
				"VALUES (" . join(', ', map { $dbh->quote($fields->{$_}) } @keys) . ")"
			);
		}
	} else {	# ARRAY
		my $cmd1 = "INSERT INTO $tbl (";
		my $cmd2 = ") VALUES (";
		for (my $i=0; $i < @$fields; $i+=2) {
			if ($noquotes) {
				$cmd1 .= $fields->[ $i ];
				$cmd2 .= $fields->[ $i+1 ];
			} else {
				$cmd1 .= $dbh->quote_identifier($fields->[ $i ]);
				$cmd2 .= $dbh->quote($fields->[ $i+1 ]);
			}
			if ($i+2 < @$fields) {
				$cmd1 .= ", ";
				$cmd2 .= ", ";
			}
		}
		$res = $self->query($cmd1 . $cmd2 . ")");
	}

	$self->finish;
	return $res;
}

# updates an existing row in the table. The 2nd param is either a HASH or ARRAY
# ref of field key => values.
# ->update('table', { key=val...}, [where], noquotes[0|1])
sub update {
	my $self = shift;
	my $tbl = shift;
	my $fields = shift;			# must be HASH or ARRAY ref
	my $where = shift;
	my $noquotes = shift || 0;
	my $dbh = $self->{dbh};
	my $cmd = "UPDATE $tbl SET ";

	if (ref $fields eq 'HASH') {
		if ($noquotes) {
			$cmd .= join(', ', map { $_ . "=" . $fields->{$_}  } keys %$fields);
		} else {
			$cmd .= join(', ', map { $dbh->quote_identifier($_) . "=" . $dbh->quote($fields->{$_}) } keys %$fields);
		}
	} else {	# ARRAY
		my @keys = ();
		my @values = ();
		for (my $i=0; $i < @$fields; $i+=2) {
			push(@keys, $fields->[$i]);
			push(@values, $fields->[$i+1]);
		}
		if ($noquotes) {
			for (my $i=0; $i < @$fields; $i+=2) {
				$cmd .= $fields->[$i] . "=" . $fields->[$i+1];
				$cmd .= ", " if $i+2 != @$fields;
			};
		} else {
			for (my $i=0; $i < @$fields; $i+=2) {
				$cmd .= $dbh->quote_identifier($fields->[$i]) . "=" . $dbh->quote($fields->[$i+1]);
				$cmd .= ", " if $i+2 != @$fields;
			};
		}
	}
	$cmd .= " WHERE " . $self->where($where) if defined $where;
	my $res = $self->query($cmd);
	return $res;
}

# Perform a simple select on a SINGLE table. For more complex selects you must
# roll your own queries. Only the first row is returned (it's assumed that calls
# to this method only want the first row anyway). The values are returned in an
# array. No column keys are included. ->select('table', [ keys ] || 'key',
# where, order)
sub select {
	my $self = shift;
	my $tbl = shift;
	my $fields = shift;	# must be an array ref or single string of a field name
	my $where = shift;
	my $order = shift;	# simple string. must be formatted properly before passing in
	my $cmd = "";
	my @row = ();

	$fields = [ $fields ] unless ref $fields eq 'ARRAY';

	$cmd  = "SELECT " . join(", ", map { ($_ ne '*') ? $self->{dbh}->quote_identifier($_) : '*' } @$fields) . " FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$cmd .= " ORDER BY $order " if defined $order;
	$cmd .= " LIMIT 1";
	$self->query($cmd);
	if ($self->{sth}) {
		@row = $self->{sth}->fetchrow_array;
	} else {
#		$self->warn($self->errstr);
	}

	return $row[0] if (scalar @$fields == 1);
	return wantarray ? @row : [ @row ];
}

# returns all rows of data from the $cmd given as a hash for each row
# ->get_rows_hash(cmd)
sub get_rows_hash {
	my $self = shift;
	my $cmd = shift;
	my @rows;

	return unless $self->query($cmd, @_);

	while (my $data = $self->{sth}->fetchrow_hashref) {
		push(@rows, { %$data });			# make a copy of the hash, do not keep original reference
	}
	return wantarray ? @rows : \@rows;
}

# returns the next row of data from the $cmd given (or from a previous query) as
# a hash
# ->get_row_hash(cmd)
sub get_row_hash {
	my $self = shift;
	my $cmd = shift;

	$self->query($cmd, @_) if $cmd;
#	return unless $self->query($cmd);
	return $self->{sth}->fetchrow_hashref;
}

# returns all rows of data from the $cmd given as an array for each row
# ->get_rows_array(cmd)
sub get_rows_array {
	my $self = shift;
	my $cmd = shift;
	my @rows;

	return unless $self->query($cmd, @_);

	while (my $data = $self->{sth}->fetchrow_arrayref) {
		push(@rows, [ @$data ]);			# make a copy of the array, do not keep original reference
	}
	return wantarray ? @rows : \@rows;
}

# returns the next row of data from the $cmd given (or from a previous query) as
# an array
# ->get_row_array(cmd)
sub get_row_array {
	my $self = shift;
	my $cmd = shift;

	$self->query($cmd, @_) if $cmd;
#	return unless $self->query($cmd);
	my $row = $self->{sth}->fetchrow_arrayref;
	return wantarray ? defined $row ? @$row : () : $row;
}

# returns an array of items. All columns from the rows returned are combined
# into a single array. mainly useful when used to return a single column from
# multiple rows.
# ->get_list(cmd)
sub get_list {
	my $self = shift;
	my $cmd = shift;
	my @list;

	return unless $self->query($cmd, @_);

	while (my $data = $self->{sth}->fetchrow_arrayref) {
		push(@list, @$data);
	}
	return wantarray ? @list : \@list;
}

# returns the total rows in a table, optionally matching on the given WHERE
# clause
# ->count(table, where)
sub count {
	my $self = shift;
	my $tbl = shift;
	my $where = shift;
	my $cmd;
	my $count;

	$cmd = "SELECT COUNT(*) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$count);
		$self->{sth}->fetch;
	} else {
#		$self->errlog($self->errstr);
	}
	$self->finish;
	return $count || 0;
}

# returns the MAX() value of a variable in a table
# $var defaults to 'id'
# ->max(table, var, where)
sub max {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $where = shift;
	my $max;
	my $cmd;

	$cmd = "SELECT MAX($var) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$max);
		$self->{sth}->fetch;
	}
	$self->finish;
	return $max || 0;
}

# returns the MIN() value of a variable in a table
# $var defaults to 'id'
# ->min(table, var, where)
sub min {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $where = shift;
	my $min;
	my $cmd;

	$cmd = "SELECT MIN($var) FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	$self->query($cmd);
	if ($self->{sth}) {
		$self->{sth}->bind_columns(\$min);
		$self->{sth}->fetch;
	}
	$self->finish;
	return $min || 0;
}

# returns the next usable numeric ID for a table (since we're not using
# auto_increment on any tables) $var defaults to 'id'
# ->next_id(table, var)
sub next_id {
	my $self = shift;
	my $tbl = shift;
	my $var = shift || 'id';
	my $name = 'next_id_for_' . $var . '_in_' . $tbl;
	
	# Prepare a statement for this table if it doesn't exist already, since
	# next_id is called a lot this should increase performance slightly.
	if (!$self->prepared($name)) {
		$self->prepare($name, "SELECT IFNULL(MAX($var),0)+1 FROM $tbl");
	}
	return $self->execute_selectcol($name);
}

sub last_insert_id {
	return $_[0]->{dbh}->last_insert_id(undef, undef, undef, undef) || 0;
}

# Deletes a row from the table based on the WHERE clause. 
# ->delete(table, where)
sub delete {
	my $self = shift;
	my $tbl = shift;
	my $where = shift;
	my $cmd;

	$cmd  = "DELETE FROM $tbl ";
	$cmd .= "WHERE " . $self->where($where) if defined $where;
	my $res = $self->query($cmd);
	$self->finish;
	return $res;
}

sub droptable {
	my $self = shift;
	my $tbl = shift;
	return $self->{dbh}->do("DROP TABLE $tbl");
}

sub truncate {
	my $self = shift;
	my $tbl = shift;
	return $self->{dbh}->do("TRUNCATE TABLE $tbl");
}

sub begin { $_[0]->{dbh}->begin_work; }
sub commit { $_[0]->{dbh}->commit; }
sub rollback { $_[0]->{dbh}->rollback; }

# starts a new generic SQL query. $cmd can be a query string or a previously
# prepared statement handle.
# ->query(cmd)
sub query {
	my ($self, $cmd, @bind) = @_;
	my ($rv, $attempts, $done, $sth);
	$self->{lastcmd} = ref $cmd ? $cmd->{Statement} : $cmd;

	$attempts = 0;
	do {
		$sth = ref $cmd ? $cmd : $self->{dbh}->prepare($cmd);
		if (!$sth) {
			return $self->fatal_safe("Error preparing DB query:\n$cmd\n" . $self->errstr . "\n--end--");
		} 

		$attempts++;
		$rv = $sth->execute(@bind);

		if (!$rv) {
			# 1040 = Too many connections
			# 1053 = Server shutdown in progress (can happen with a
			#        'kill <pid>' is issued via the mysql client)
			# 2006 = Lost connection to MySQL server during query
			# 2013 = MySQL server has gone away
			if (grep { $self->errno eq $_ } qw( 2013 2006 1053 1040 )) {
				$self->warn_safe("DB connection was lost (errno " . $self->errno . "); Attempting to reconnect #$attempts");
				sleep(1);	# small delay

				my $connect_attempts = 0;
				do {
					$connect_attempts++;
					if ($connect_attempts > 1) {
						$self->warn_safe("Re-attempting to establish a DB connection (#$connect_attempts)");
						sleep(3);
					}
					$self->connect;
				} while (!ref $self->{dbh} and $connect_attempts <= 10);
				if (!ref $self->{dbh}) {
					return $self->fatal_safe("Error re-connecting to database using dsn \"$self->{dsn}\":\n" . $DBI::errstr);
				}
			} else {
				# don't try to reconnect on most errors
				$done = 1;
			}
		}
	} while (!$rv and !$done and $attempts <= 10);

	if ($rv) {
		# do nothing, allow caller to work with the statement handle directly ....
		;;; $self->debug9(join(" ", split(/\s*\015?\012\s*/, $cmd)),20);
	} else {
		return $self->fatal_safe("Error executing DB query:\n$cmd\n" . $self->errstr . "\n--end of error--");
	}
	
	return $self->{sth} = $sth;
}

# "do" performs a simple non-select query and we don't care about the result.
sub do {
	my ($self, $cmd, @bind) = @_;

	# interpolate table names in query to real table names
	$cmd =~ s/\s([tc])_(\w+)/' ' . ($self->{$1.'_'.$2} || $1.'_'.$2)/ge;

	$self->{lastcmd} = $cmd;
	;;;$self->debug9($cmd, 20);
	return $self->{dbh}->do($cmd, undef, @bind);
}

# _expr_* methods are called with the prototype: ($self, $quoted_key, $key, $value)
sub _expr_max { "IF($_[1] > $_[3], $_[1], $_[3])" }
sub _expr_min { "IF($_[3] < $_[1], $_[3], $_[1])" }

# _calc_* methods are called with the prototype: ($self, $quoted_key1, $quoted_key2, ...)
sub _calc_percent 	{ "IFNULL($_[1] / $_[2] * 100, 0.00)" }
sub _calc_percent2 	{ "IFNULL($_[1] / ($_[1] + $_[2]) * 100, 0.00)" }
sub _calc_ratio 	{ "IFNULL($_[1] / $_[2], $_[1])" }
sub _calc_ratio_minutes { "IFNULL($_[1] / ($_[2] / 60), 0.00)" }

sub _calc {
	my ($self, $tbl, $type) = @_;
	my $func = "_calc_" . $type->[0];
	return $self->$func( map { $self->{dbh}->quote_identifier($_) } @$type[1 .. $#$type] );
}

# updates compiled stats data. this always assume a matching row exists, if not,
# it does nothing. the ONLY variable this does not update is 'lastdate' there's
# no way to determine what the variable is until the old historical rows are
# deleted (at least it's not easy to do)
sub update_stats {
	my ($self, $table, $data, $types, $where) = @_;
	my ($exists, $qk, $func, $set, $calcset, $t);
	my $primary = 'dataid';
	my $dbh = $self->{dbh};
	my $ok = 1;
	return unless scalar keys %$data;		# nothing to do if the hash is empty

#	$exists = $self->select($table, $primary, $where);
#	if ($exists) {
		$set = [];
		foreach my $key (keys %$data) {
			next unless exists $types->{$key};
			next if ref $types->{$key};
			$t = $types->{$key};
			$qk = $dbh->quote_identifier($key);

			if ($t eq '=') {
				push(@$set, $qk, $dbh->quote($data->{$key}));
			} elsif ($t eq '+') {
				push(@$set, $qk, $qk . " + " . $data->{$key});
			} elsif ($t eq '>') {
				push(@$set, $qk, $self->_expr_max($qk, $key, $dbh->quote($data->{$key})));
			} elsif ($t eq '<') {
				push(@$set, $qk, $self->_expr_min($qk, $key, $dbh->quote($data->{$key})));
			} else {
				# unknown TYPE 
			}
		}
		if (@$set) {
			foreach my $key (grep { ref $types->{$_} } keys %$types) {
				push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
			}
			$ok = $self->update($table, $set, $where, 1);		# 1 = no `quotes`
		}
#	}
	return $ok;
}

# saves stats data. $statdate is only included for compiled data sets and is
# used to determine the firstdate and lastdate.
sub save_stats {
	my ($self, $table, $data, $types, $where, $statdate, $primary) = @_;
	my ($exists, $qk, $func, $set, $calcset, $t);
	my $docalc = (index($table, $self->{dbtblcompiledprefix}) == 0);	# do not use calc'd fields on non-compiled tables
	my $dbh = $self->{dbh};
	return unless scalar keys %$data;		# nothing to do if the hash is empty
	$primary ||= 'dataid';

	$exists = $where ? $self->select($table, $primary, $where) : undef;
	if ($exists) {
		$set = [];
		foreach my $key (keys %$data) {
			next unless exists $types->{$key};
			next if ref $types->{$key};
			$t = $types->{$key};
			$qk = $dbh->quote_identifier($key);

			if ($t eq '=') {
				push(@$set, $qk, $dbh->quote($data->{$key}));
			} elsif ($t eq '+') {
				push(@$set, $qk, $qk . " + " . $data->{$key});
			} elsif ($t eq '>') {
				push(@$set, $qk, $self->_expr_max($qk, $key, $dbh->quote($data->{$key})));
			} elsif ($t eq '<') {
				push(@$set, $qk, $self->_expr_min($qk, $key, $dbh->quote($data->{$key})));
			} else {
				# unknown TYPE 
			}
		}
		if (@$set) {
			if ($docalc) {
				push(@$set, 'lastdate', $dbh->quote($statdate)) if $statdate;
				foreach my $key (grep { ref $types->{$_} } keys %$types) {
					push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
				}
			}
			$self->update($table, $set, $where, 1);		# 1 = no `quotes`
		}
	} else {	# INSERT NEW ROW
		$exists = $self->next_id($table, $primary);
		$set = [];
		# don't add the primary key if it already exists in the @where list
		push(@$set, $primary, $exists) unless grep { $_ eq $primary } @$where;
#		push(@$set, @$where, %$data);
		push(@$set, @$where) if $where;
		foreach my $key (keys %$types) {
			push(@$set, $key, $data->{$key}) if exists $data->{$key};
#			push(@$set, $key, $data->{$key}) if exists $data->{$key} or !ref $types->{$key};
		}
		# quote the current data in the set (since we don't want the insert() sub to do it)
		for (my $i=0; $i < @$set; $i+=2) {
			$set->[$i] = $dbh->quote_identifier($set->[$i]);
			$set->[$i+1] = $dbh->quote($set->[$i+1]);
		}
		if ($docalc) {
			if ($statdate) {
				my $sd = $dbh->quote($statdate);
				push(@$set, 'firstdate', $sd, 'lastdate', $sd);
			}
			foreach my $key (grep { ref $types->{$_} } keys %$types) {
#				push(@$set, map { $dbh->quote_identifier($_), 0 } grep { !exists $data->{$_} } @{$types->{$key}}[1,2]) if $self->type eq 'sqlite';
				push(@$set, $dbh->quote_identifier($key), $self->_calc($table,$types->{$key}));
			}
		}
		$self->insert($table, $set, 1);				# 1 = no `quotes`
	}
#	print $self->lastcmd,"\n\n";
	return $exists;
}

# return a valid "LIMIT x,y" statement string.
sub limit {
	my ($self,$limit,$start) = @_;
	return '' unless defined $limit and defined $start;
	my $sql = '';
	if (defined $limit and !defined $start) {
		$sql = "LIMIT $limit";
	} elsif (defined $limit and defined $start) {
		$sql = "LIMIT $start,$limit";
	}
	return $sql;
}

# returns a SQL string to use for a statement loading historical stats ignores
# calculated type keys.
sub _values {
	my ($self, $types, $expr) = @_;
	my $values = "";
	$expr ||= '+ > < ~ $';

	foreach my $key (keys %$types) {
		my $type = $types->{$key};
		if (ref $type) {
			next unless index($expr, '$') >= 0;
			# ignoring calculated fields

		} elsif ($type eq '+') {
			$values .= "SUM($key) $key, " if index($expr, '+') >= 0;
		} elsif ($type eq '>') {
			$values .= "MAX($key) $key, " if index($expr, '>') >= 0;
		} elsif ($type eq '<') {
			$values .= "MIN($key) $key, " if index($expr, '<') >= 0;
		} elsif ($type eq '~') {
			$values .= "AVG($key) $key, " if index($expr, '~') >= 0;
		} 
	}
	$values = substr($values, 0, -2) if $values; 	# trim trailing comma: ", "
	return $values;
}

# returns true if the DB object allows queries with sub-selects
sub subselects () { undef }

# returns the version of the DB being used. Up to 3 parts, ie: "4.1.12"
# do not include any non-numeric values.
sub db_version () { '0.0.0' }

sub type () { '' }

# do something on the server, we don't care what.. Anything to keep the
# connection alive and not idle.
sub idle {
	my ($self) = @_;
	$self->{dbh}->do("SELECT VERSION()");
}

# cleans up the last used statement handle and free its memory
sub finish { undef $_[0]->{sth} if $_[0]->{sth} }

# returns the last query command given, optionally with bound values given
sub lastcmd {
	if (@_ <= 1) {
		return $_[0]->{lastcmd};
	} else {
		my $self = shift;
		my @bind = ref $_[0] ? @{$_[0]} : @_;
		my $cmd = $self->{lastcmd};
		$cmd =~ s/\?/$_/ for map { $self->{dbh}->quote($_) } @bind;
		return $cmd;
	}
}

# Quotes an identifier (table column name, table name, database name, etc...)
sub qi { $_[0]->{dbh}->quote_identifier($_[1]) }

# Quotes a literal value
sub quote { $_[0]->{dbh}->quote($_[1]) }

sub err { $_[0]->{dbh}->err || $DBI::err }
sub errstr { $_[0]->{dbh}->errstr || $DBI::errstr }
sub lasterr { $_[0]->{lasterr} || '' }

sub fatal_safe {
	my $self = shift;
	if ($self->{fatal}) {
		$self->SUPER::fatal_safe(@_);
	} else {
		#$self->SUPER::warn_safe(@_);
		$self->{lasterr} = shift;
	}
	return '';
}

1;

