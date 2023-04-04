UPDATE config
SET description="Email signatures will only be applied to users that have an email address that matches the School Email Domain.  Available tokens are: [title] [firstname] [surname] [fullname] [email] [position]."
WHERE name="email_signature";
