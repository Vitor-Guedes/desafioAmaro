create table if not exists `product`
(
	`id` int primary key ,
    `name` varchar(150) not null
) engine=InnoDB;

create table if not exists `tag`
(
	`id` int primary key auto_increment,
    `label` varchar(150) not null
) engine=InnoDB;

create table if not exists `product_tag`
(
	`id` int primary key auto_increment,
    `product_id` int,
    `tag_id` int,
    foreign key (`product_id`) references `product` (`id`),
    foreign key (`tag_id`) references `tag` (`id`)
) engine=InnoDB;