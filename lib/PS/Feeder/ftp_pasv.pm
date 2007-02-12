# PASSIVE mode FTP support
package PS::Feeder::ftp_pasv;

use strict;
use warnings;
use base qw( PS::Feeder::ftp );

our $VERSION = '1.00';

sub _parsesource {
	my $self = shift;
	my $res = $self->SUPER::_parsesource;
	$self->{_opts}{Passive} = 1;
	return $res;
}


1;
