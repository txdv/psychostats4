package PS::DB::mysql;

use strict;
use warnings;
use base qw( PS::DB );
use DBI;
use Data::Dumper;
use Carp;

sub init {
	my $self = shift;

	$self->SUPER::init;

	# setup our database connection
	if (!$self->{dbh}) {
		my $dsn = 'DBI:' . $self->{dbtype} . ':database=' . $self->{dbname};
		$dsn .= ';host=' . $self->{dbhost} if defined $self->{dbhost};
		$dsn .= ';port=' . $self->{dbport} if defined $self->{dbport};
#		$dsn .= ';' . $self->{dbopts} if defined $self->{dbopts};
		$self->{dbh} = DBI->connect($dsn, $self->{dbuser}, $self->{dbpass}, {PrintError => 0, RaiseError => 0, AutoCommit => 1});
		$self->fatal("Error connecting to database using dsn \"$dsn\":\n" . $DBI::errstr) unless ref $self->{dbh};
	}

	$self->{dbh}{mysql_auto_reconnect} = 1;		# always try to reconnect if we loose connection

	# setup our version number and if we're capable of doing sub-selects
	my ($v) = $self->get_list("SELECT VERSION()");
	if ($v) {
		$v =~ s/-.+$//;		# remove trailing trash (anything after the dash)
		$self->{version} = join('.', grep { /^\d+$/ } map { defined $_ ? $_ : 0 } split(/\./, $v, 3));
		$self->{subselects} = ($self->version(2) >= 4.1);		# 4.1 is required for sub-selects
	} else {
		$self->{subselects} = 0;		# don't assume 
		$self->{version} = "0.0.0";
	}

	if ($self->version(2) >= 4.1) {
		$self->{dbh}->do("SET NAMES 'utf8'");
		$self->{dbh}->do("SET CHARACTER SET 'utf8'");
	}

	$self->debug("DB MYSQL v" . $self->{version} . " initialized" . ($self->subselects ? ' (sub-selects supported)' : ''));
}

sub type { "mysql" }
sub subselects { $_[0]->{subselects} }
sub version { @_==1 ? $_[0]->{version} : join('.', grep { defined } (split(/\./, $_[0]->{version}))[0..$_[1]-1]) }

sub init_database {
	my $self = shift;
#	my $drh = DBI->install_driver('mysql');

#	print Dumper(scalar $self->tableinfo('plr_data'));
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
	my @cols = ref $_[0] ? @$_[0] : @_;
	my $cmd = "ALTER TABLE $tbl ADD PRIMARY KEY ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
#	print "$cmd\n";
	$self->query($cmd) or $self->fatal("Error creating primary index on table $tablename: $self->errstr");
}

sub create_unique_index {
	my $self = shift;
	my $tablename = shift; 
	my $name = shift;
	my @cols = ref $_[0] ? @$_[0] : (@_ ? @_ : ($name));
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	$name = $self->{dbh}->quote_identifier( $name );
	my $cmd = "ALTER TABLE $tbl ADD UNIQUE $name ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
#	print "$cmd\n";
	$self->query($cmd) or $self->fatal("Error creating unique index on table $tablename: $self->errstr");
}

sub create_index {
	my $self = shift;
	my $tablename = shift;
	my $tbl = $self->{dbh}->quote_identifier( $tablename );
	my $name = $self->{dbh}->quote_identifier( shift );	
	my @cols = ref $_[0] ? @$_[0] : @_;
	my $cmd = "ALTER TABLE $tbl ADD INDEX $name ( " . join(", ", map { $self->{dbh}->quote_identifier($_) } @cols) . " )";
#	print "$cmd\n";
	$self->query($cmd) or $self->fatal("Error creating index on table $tablename: $self->errstr");
}

sub _create_footer { ") DEFAULT CHARACTER SET utf8" }

1;

