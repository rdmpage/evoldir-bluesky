# Pushing EvolDir posts to BlueSky

A simple script to fetch posts from the [EvolDir mailing list](https://evol.mcmaster.ca/brian/evoldir.html) run by Brian Golding.

When run the script fetches the last three days of EvolDir posts as text files, parses them, and extracts the individual posts. It computes the MD5 hash of the text of each post and stores each post using the hash as the file name. This enables us to test whether we’ve encountered the post before. By fetching the last three days we minimise the chances that we miss a post.

Each new post is prepared for BlueSky by using OpenAI to construct a short summary of the post, asking it to include a single relevant URL (which we hope is a link to a job website, a conference announcement, etc.). We append hash tags based on the heading of the post.

The [BlueSky API](https://docs.bsky.app/blog/create-post) is then used to enhance the post by extracting “facets” such as ash tags and links. We attempt to construct a “card” for a link by fetching the content pointed to by the link and looking for `og:title`, `og:description`, and `og:image` tags in the web page. This assumes that the web site supports [Open Graph Markup](https://developers.facebook.com/docs/sharing/webmasters/). In the future I may look at supporting other tags, as well as [oEmbed](https://oembed.com).

The enhanced post is then sent to BlueSky.


