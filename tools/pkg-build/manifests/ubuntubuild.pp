# Puppet Setup for Ubuntu Build
exec { 'apt-get update':
        command        => '/usr/bin/apt-get update',
}
package { 'packaging-dev':
        ensure  => latest,
}
package { 'dh-make':
        ensure  => latest,
        require => Package['packaging-dev'],
}
package { 'git-buildpackage':
        ensure  => latest,
        require => Package['dh-make'],
}
package { 'reprepro':
        ensure  => latest,
}
package { 'php5-cli':
        ensure  => latest,
}
package { 'php5-curl':
        ensure  => latest,
}
package { 'openjdk-7-jre':
        ensure  => latest,
}
package { 'python-software-properties':
        ensure  => latest,
}
package { 'rabbitmq-server':
	ensure  => absent,
}
file { '/usr/bin/sass':
	ensure => 'link',
	target => '/usr/local/bin/sass',
}
#apt::ppa { 'ppa:chris-lea/node.js': }
#package { 'nodejs':
#        ensure  => latest,
#        require => Apt::Ppa['ppa:chris-lea/node.js'],
#}
file { '/home/vagrant/.gbp.conf':
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0640',
        require => Package['git-buildpackage'],
        content => '[DEFAULT]
[git-buildpackage]
export-dir = ../build-area/
tarball-dir = ../tarballs/',
}
file { '/home/vagrant/tarballs':
        ensure  => 'directory',
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0640',
}
file { '/home/vagrant/build-area':
        ensure  => 'directory',
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0640',
}
file { '/home/vagrant/.ssh':
        ensure  => 'directory',
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0600',
}
file { '/home/vagrant/.ssh/id_rsa':
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0600',
        content => '-----BEGIN RSA PRIVATE KEY-----
MIIEogIBAAKCAQEAt+x4IkKboX6qNE5+c05J5NkO81/xDACCDaV6ldKlXWTSaWLQ
QN6FgRo7grCjlgYbLB12jkEMGU3YeZ3G77RsVoOumpurYeFVitZGHY+Xo+GbVBTa
yN1fBo+6igKuTsqVMlu+UH4RGa0x2nc9qxCWEO+rZp06BCtULX1uAxUldJF96LAG
mVONCtKoUHOZxtDWJODigRXa/NX3pII5TI5mLCdE07ERCWXHrm5f6ErTcpKWJk2W
s3TX7Sf6jRankSgupBNc9/l3vtyK6IcT8kTkAtTm3aH4BtbIMUN4sVNTykVC8mSJ
VkPeB32mX+/f3BC7T7ZKKiRKLfWF9Kxxw6ydlQIDAQABAoIBAF6KH2pE4KiPaj++
xULR0Cvg4s142Hd531/SWoOepj+ubnWbRD7D4s3sxQA1ebDI7LitYF5Y0zZCjG5f
Cn32OOXzhBGWyO5kHEtkPuA9Cg5EDkqW+arcF1dRE+qS3DRF75DWFapcUr/nBcQt
HLbMrMaB25HAaazw9LD5U04kVX9n1R2JJA17p46NSRCa7pVeHZgIuvAkCZn9ny9s
QfEaHN8zVNLxS1wsc9X30WizVI2Uq0W79UhDUYipXHzy8zfLnXPVwtq8MJJByyt6
StqpICRVqsZot1Oq2uJXEGsTyiB1bcRYNBZQWIaM1RCj/VkWmsnsxpAr6REK8aiQ
lW7W4gECgYEA9Qvtty8KtFLmOFdWM1M2Cq24FCDM0zZBAQMDPi32Q17L4WEhnEmG
xTNSi8DrGbfahTO3O6gJ3Sk51EB+VasycqDp5TrTTKxUxP5VmG1e54/WEBZjLvns
sYIYuLTQvFNtYj8kPHHANBEjagoL+Gd2SenwMzFcWW0CT6qGXNiAKhUCgYEAwCUc
VeKVtsFbCLDeHmo031Baw77eAqk/IoBtg8qc/AsCVr8y+h1pyB9D7YBZbmmlsSl9
tAI9qoIwXJ39EEHBBAVD8E6EYgcTSWWmLvqHeWjluOOvm4zTXL+76EjlvTVa8zDx
6svIXQrSjUXXB+d5yhX1/hGuOOzf1A5qdftxBYECgYBUNvxEV1Nrc44RUNqcGv3Y
n6lCqZ8a+2tNqLZlmTAoKR0SYOpHJvG9Ng0pYGyr0wazfnl7dB2ntzJboqqIUKSu
xmR71kKl+BfN98Z88yGJnWIo9ziw/U1+F8LdP674+gb+m8qdR06R1k4AdGF2xj/d
2RsmzND7Hr6LmIRCxlkNnQKBgBJ7f15DjWMm9jq/yJ3kWXGhqRlrIq/e19PZxq8G
rlMQPAlcGus0KRi26THPRsr4HpgMBatrC8N7AmvmRpFpwcdBOmfDJbjINXK59GJQ
dveWv4IpZJrx0OL7Dngpiu/IUhdSffK3524LvHUaYrS/71chBaiKytdu8ZjUSilP
JnABAoGADZ2bcoc6OvFywSMcbm7g1u8Szj+NcxhLZHmZNhaEp5DG3TpuYhMV2bFV
n8Ua10wUR+cbnlQKu2r7cTb/NPGQIblzFMq4x7YkLeXaedl42581FIUQgMxjtRyB
Mi5zvYQjDP8nnp+mChjb99WcTRBPthFNP4Dyr0AEPS7hjbML2os=
-----END RSA PRIVATE KEY-----',
        require => File['/home/vagrant/.ssh'],
}
file { '/home/vagrant/.ssh/known_hosts':
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0644',
        content => 'github.com,207.97.227.239 ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAq2A7hRGmdnm9tUDbO9IDSwBK6TbQa+PXYPCPy6rbTrTtw7PHkccKrpp0yVhp5HdEIcKr6pLlVDBfOLX9QUsyCOV0wzfjIJNlGEYsdlLJizHhbn2mUjvSAHQqZETYP81eFzLQNnPHt4EVVUh7VfDESU84KezmD5QlWpXLmvU31/yMf+Se8xhHTvKSCZIFImWwoG6mbUoWf9nzpIoaSjB+weqqUUmpaaasXVal72J+UX2B+2RPW3RcT0eOzQgqlJL3RKrTJvdsjE3JEAvGq3lGHSZXy28G3skua2SmVi/w4yCE6gbODqnTWlg7+wC604ydGXA8VJiS5ap43JXiUFFAaQ==
[ssh.github.com]:443,[207.97.227.248]:443 ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAq2A7hRGmdnm9tUDbO9IDSwBK6TbQa+PXYPCPy6rbTrTtw7PHkccKrpp0yVhp5HdEIcKr6pLlVDBfOLX9QUsyCOV0wzfjIJNlGEYsdlLJizHhbn2mUjvSAHQqZETYP81eFzLQNnPHt4EVVUh7VfDESU84KezmD5QlWpXLmvU31/yMf+Se8xhHTvKSCZIFImWwoG6mbUoWf9nzpIoaSjB+weqqUUmpaaasXVal72J+UX2B+2RPW3RcT0eOzQgqlJL3RKrTJvdsjE3JEAvGq3lGHSZXy28G3skua2SmVi/w4yCE6gbODqnTWlg7+wC604ydGXA8VJiS5ap43JXiUFFAaQ==|1|jqjKG/xbLvrEYkBw4Ts+OX7Mo/Y=|zcwy3fLb2Yj0GFtiTIwXdQ+E5i4= ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAq2A7hRGmdnm9tUDbO9IDSwBK6TbQa+PXYPCPy6rbTrTtw7PHkccKrpp0yVhp5HdEIcKr6pLlVDBfOLX9QUsyCOV0wzfjIJNlGEYsdlLJizHhbn2mUjvSAHQqZETYP81eFzLQNnPHt4EVVUh7VfDESU84KezmD5QlWpXLmvU31/yMf+Se8xhHTvKSCZIFImWwoG6mbUoWf9nzpIoaSjB+weqqUUmpaaasXVal72J+UX2B+2RPW3RcT0eOzQgqlJL3RKrTJvdsjE3JEAvGq3lGHSZXy28G3skua2SmVi/w4yCE6gbODqnTWlg7+wC604ydGXA8VJiS5ap43JXiUFFAaQ==',
        require => File['/home/vagrant/.ssh'],
}
file { '/home/vagrant/.gitconfig':
        owner   => 'vagrant',
        group   => 'vagrant',
        mode    => '0644',
        content => '[user]
        name = Alaress (Schoolbox Signing Key)
        email = support@schoolbox.com.au',
}
