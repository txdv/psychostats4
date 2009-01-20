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
package PS::DBI::mysql;

use strict;
use warnings;
use base qw( PS::DBI );

use DBI;
use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;
	$self->SUPER::init;

	# setup our database connection
	if (!$self->{dbh}) {
		$self->{dsn} = 'DBI:' . $self->{dbtype} . ':database=' . $self->{dbname};
		$self->{dsn} .= ';host=' . $self->{dbhost} if defined $self->{dbhost};
		$self->{dsn} .= ';port=' . $self->{dbport} if defined $self->{dbport};
		$self->{dsn} .= ';mysql_compression=' . $self->{dbcompress} if $self->{dbcompress};
		#$self->{dsn} .= ';' . $self->{dbopts} if defined $self->{dbopts};
		$self->connect;
		$self->fatal("Error connecting to database using dsn \"$self->{dsn}\":\n" . $DBI::errstr) unless ref $self->{dbh};
	}

	# mysql_auto_reconect is ignored if AutoCommit is off (0)
	$self->{dbh}{mysql_auto_reconnect} = 1;		# always try to reconnect if we loose connection

	# mysql_server_prepare doesn't work, at least for me, which is a shame
	# because this will speed up DB accesses, in theory.
	#$self->{dbh}{mysql_server_prepare} = 1;

	# mysql_enable_utf8 is only implemented in DBD::mysql v4.0 (4.004+ recommended)
	#$self->{dbh}{mysql_enable_utf8} = 1;		# assume all text columns are UTF8
	
	my $v = $self->db_version(2);
	
	# define a constant sub that returns true or false if sub-selects are
	# supported or not.
	if ($v >= 4.1) {
		no strict 'refs';
		*subselects = sub () { 1 };
	} else {
		no strict 'refs';
		*subselects = sub () { 0 };
	}

	# setup our connection environment based on the mysql version
	if ($v >= 4.1) {
		$self->{dbh}->do("SET NAMES 'utf8'");
		$self->{dbh}->do("SET CHARACTER SET 'utf8'");
	}
	if ($v >= 5.0) {
		# disable strict mode to avoid some common errors regarding
		# table inserts. we'd rather have warnings.
		$self->{dbh}->do("SET SESSION sql_mode=''");
	} 

	$self->debug1("DB MYSQL v" . $self->db_version . " initialized.");
	return $self;
}

sub connect {
	my ($self) = @_;
	$self->{dbh} = undef;	# forces any previously loaded driver to be DESTROY'ed
	$self->{dbh} = DBI->connect($self->{dsn}, $self->{dbuser}, $self->{dbpass}, {
		PrintError => 0, RaiseError => 0, AutoCommit => 1
	});
	return $self->{dbh};
}

sub type () { "mysql" }

#sub subselects { $_[0]->{subselects} }	# defined in init()

# returns the normalized version string for the database up to X octets
sub db_version {
	my ($self, $octets) = @_;
	if (!$self->{db_version}) {
		my $st = $self->{dbh}->prepare("SELECT VERSION()");
		if ($st->execute) {
			my $ver;
			$st->bind_columns(\$ver);
			$st->fetch;
			$ver =~ s/-.+$//;	# remove trailing "-crap"
			$self->{db_version} = [ split(/\./, $ver) ];
			$st->finish;
		}
	}
	$octets = @{$self->{db_version}} if !defined $octets
		or $octets < 1
		or $octets > @{$self->{db_version}};
	return join('.', @{$self->{db_version}}[0..$octets-1]);
}

# toggle autocommit on/off
sub autocommit {
	my ($self, $auto) = @_;
	$self->{dbh}->{AutoCommit} = $auto ? 1 : 0;
}

sub table_exists {
	my $self = shift;
	my $tablename = shift; #$self->tbl( shift );
	my $list = $self->get_list("SHOW TABLES");
	foreach (@$list) {
		return 1 if $_ eq $tablename;
	}
	return 0;
}

sub _explain {
	my $self = shift;
	my $tbl = $self->{dbh}->quote_identifier(shift);			# table will already have its prefix
	my $fields = {};
	my $rows = $self->get_rows_hash("EXPLAIN $tbl");

#          'name' => {
#                      'Field' => 'name',
#                      'Type' => 'varchar(64)',
#                      'Extra' => '',
#                      'Default' => 'noname',
#                      'Null' => '',
#                      'Key' => 'UNI'
#                    },
	foreach my $row (@$rows) {
		$fields->{ $row->{Field} } = { map { lc $_ => $row->{$_} } keys %$row };
	}
	return wantarray ? %$fields : $fields;
}

sub create_primary_index {
	my $self = shift;
	my $tablename = shift; 
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my @cols = ref $_[0] ? @{$_[0]} : @_;
	my $cmd = "ALTER TABLE $tbl ADD PRIMARY KEY ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
	$self->query($cmd) or $self->fatal("Error creating primary index on table $tablename: $self->errstr");
}

sub create_unique_index {
	my $self = shift;
	my $tablename = shift; 
	my $name = shift;
	my @cols = ref $_[0] ? @{$_[0]} : (@_ ? @_ : ($name));
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	$name = $self->{dbh}->quote_identifier( $name );
	my $cmd = "ALTER TABLE $tbl ADD UNIQUE $name ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
	$self->query($cmd) or $self->fatal("Error creating unique index on table $tablename: $self->errstr");
}

sub create_index {
	my $self = shift;
	my $tablename = shift;
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my $name = $self->{dbh}->quote_identifier( shift );	
	my @cols = ref $_[0] ? @{$_[0]} : @_;
	my $cmd = "ALTER TABLE $tbl ADD INDEX $name ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
	$self->query($cmd) or $self->fatal("Error creating index on table $tablename: $self->errstr");
}

sub _create_footer { ") DEFAULT CHARACTER SET utf8" }

sub alter_table_add {
	my ($self, $tablename, @add) = @_;
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my $cmd = "ALTER TABLE $tbl " . join(', ', map { 'ADD ' . $_ } @add);
	return $self->query($cmd);
}

sub errno { $_[0]->{dbh}{mysql_errno} }

1;

