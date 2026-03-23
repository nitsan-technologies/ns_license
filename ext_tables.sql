--
-- Table structure for table `ns_product_license`
--
CREATE TABLE ns_product_license (
	uid int(11) NOT NULL auto_increment,
	name varchar(50) DEFAULT NULL,
	email varchar(255) NOT NULL,
	order_id varchar(50) NOT NULL,
	license_key varchar(255) NOT NULL,
	extension_key varchar(50) NOT NULL,
	product_link text,
  	documentation_link text,
	version varchar(15) DEFAULT NULL,
	lts_version varchar(15) NOT NULL,
	cs_version varchar(15) DEFAULT NULL,
	cs_lts_version varchar(15) DEFAULT NULL,
	is_life_time varchar(5) DEFAULT NULL,
	expiration_date int(11) DEFAULT '0' NOT NULL,
	domains text DEFAULT NULL,
	existing_domains text DEFAULT NULL,
	username varchar(255) NOT NULL,
	description varchar(255) DEFAULT '' NOT NULL,
	local_domains text DEFAULT NULL,
	staging_domains text DEFAULT NULL,
	license_type varchar(5) DEFAULT NULL,
	trial_extended smallint(5) unsigned DEFAULT '0' NOT NULL,
	rating float DEFAULT '0',
	downloads int(11) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid)
);

--
-- Table structure for table `ns_sync_registry`
-- Stores synchronized data in a registry format
--
CREATE TABLE ns_sync_registry (
	uid int(11) NOT NULL auto_increment,
	data_type varchar(50) NOT NULL,
	data_content longtext,
	created_at int(11) DEFAULT '0' NOT NULL,
	updated_at int(11) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid),
	UNIQUE KEY data_type (data_type)
);