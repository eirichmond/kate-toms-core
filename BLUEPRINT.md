# **House Blueprint Generation System**

## **Objective**

Create a new **House Blueprint** feature that automates the creation of a complete house page structure using the existing **MASTER template patterns** created by Fern. These can be found here: Patterns \> Kate & Toms \> \[any suffixed with MASTER\].

The Blueprint should significantly reduce manual setup by generating the parent house page and all associated child pages with the correct templates, metadata, SEO configuration, relationships and default behaviour already configured.

All newly generated pages must initially be created as **Draft** pages and remain unpublished until content has been completed and reviewed.

# **Blueprint Structure**

Using **Marsden Manor** as the reference example, creating a new house should automatically generate:

Parent

* **Marsden Manor**

Children

* **Marsden Manor | Availability | kate & tom's**  
* **Marsden Manor | Book | kate & tom's**  
* **Marsden Manor | Gallery | kate & tom's**  
* **Marsden Manor | Key Facts | kate & tom's**  
* **Marsden Manor | Things To Do | kate & tom's**

Each page should use the relevant MASTER patterns that Fern has created, containing all placeholder blocks so the content team can simply copy and paste and insert content into the predefined layouts.

# **Draft Status**

When a Blueprint is created:

* Parent page \= Draft  
* All child pages \= Draft  
* No pages should be published automatically.

# **SEO & Metadata Automation**

As part of the Blueprint creation process, automatically populate the page H1, Meta Title and Meta Description using the following logic.

Red text \- house name variable copy | **Black text** \- hardcoded standard copy

| Page | URL | H1 | Meta Title | Meta Description |
| ----- | ----- | ----- | ----- | ----- |
| Parent | https://kateandtoms.com/houses/house-name/ | \[HOUSE NAME\] | Leave blank for team to populate | Leave blank for team to populate |
| Availability | https://kateandtoms.com/houses/house-name/availability/ | \[HOUSE NAME\] Availability | \[HOUSE NAME\] | Availability | kate & tom's | View live availability and prices for \[HOUSE NAME\] |
| Book | https://kateandtoms.com/houses/house-name/book/ | \[HOUSE NAME\] Book | \[HOUSE NAME\] | Book | kate & tom's | Book \[HOUSE NAME\] today |
| Gallery | https://kateandtoms.com/houses/house-name/gallery/ | \[HOUSE NAME\] Gallery | \[HOUSE NAME\] | Gallery | kate & tom's | Explore photos of \[HOUSE NAME\]. View the bedrooms, living spaces, garden and stylish interiors |
| Key Facts | https://kateandtoms.com/houses/house-name/facts/ | \[HOUSE NAME\] Key Facts | \[HOUSE NAME\] | Key Facts | kate & tom's | View the key facts for \[HOUSE NAME\], including bedrooms, facilities, parking, dog-friendly policies, accessibility and booking information |
| Things To Do | https://kateandtoms.com/houses/house-name/more/ | \[HOUSE NAME\] Things To Do | \[HOUSE NAME\] | Things To Do | kate & tom's | Discover the best things to do near \[HOUSE NAME\] |

This should follow the naming conventions established in **Task \#382** and be generated programmatically. The page naming convention should follow the existing logic in the Tree View screenshot below:

# 

# **Additional SEO Rules**

The following pages require additional SEO configuration:

## **/availability/**

* Canonical URL should point to the parent page  
* NOINDEX  
* Excluded from sitemap

## **/book/**

* Canonical URL should point to the parent page  
* NOINDEX  
* Excluded from sitemap

# **Automatic Content Relationships**

## **1\. Gallery Images**

The first five gallery/fader images uploaded to the parent house page should automatically populate:

* Gallery page image fader  
* Availability page image fader

This should remove the need to upload the same images multiple times and replicate the behaviour of the previous website.

---

## **2\. "Houses You May Also Like"**

The related houses selected on the parent page should automatically inherit across every child page.

Editors should only need to manage this once.

---

## **3\. House Title Banner**

Within the **House Title Banner** pattern on the parent page:

Pre-populate all internal navigation buttons with the correct URL suffixes, for example:

* /availability/  
* /book/  
* /gallery/  
* /facts/  
* /more/ 

These URLs should automatically populate across every child page so they never require manual updating.

On the child page, the H1 title (e.g. Marsden Manor Gallery) needs to link back to the Parent page

---

## **4\. Key Facts Page Configuration /facts/**

As part of the Blueprint generation process, the **Key Facts** page should be automatically populated using the existing **MASTER** pattern, including all standard placeholder content, content blocks and page structure. This will provide the content team with a fully configured page containing all expected sections, allowing them to simply replace the placeholder content with house-specific information without any additional page setup. This copy can be found here: [https://docs.google.com/document/d/1PE8PQYTlc6psz0oXRvTuY2vrrb8r69W8An7dx6ji4ME/edit?tab=t.0](https://docs.google.com/document/d/1PE8PQYTlc6psz0oXRvTuY2vrrb8r69W8An7dx6ji4ME/edit?tab=t.0)

### **Bedroom Table Configuration**

The bedroom information table should be created with the following default column widths:

| Column | Width |
| :---- | :---- |
| Bedrooms | 25% |
| Sleeps | 15% |
| Beds | 30% |
| Features | 30% |

These widths should be applied automatically whenever a new Blueprint is generated to ensure consistency across all house pages and remove the need for manual table formatting.

# **Mobile Layout Defaults**

Apply the following configuration automatically within the Blueprint.

## **Content Columns**

Any content block containing paragraph text should default to:

* **Centralised on Mobile**

---

## **Button Blocks**

Any block containing buttons should default to:

* Stack buttons vertically  
* Centre aligned on mobile

This replaces the current row configuration and improves mobile presentation.

---

# **Existing Task Requirements**

Include the following previously agreed improvements as default Blueprint behaviour.

## **Task \#424**

Gallery pattern should default to:

* Thumbnail image view

---

## **Task \#421**

Within the four-image block used:

* Block 2 on the main house page  
* Final "Things To Do" section

The third image should display on mobile using the same behaviour as the legacy website.

---

# **Navigation**

Every navigation button generated within the Blueprint should automatically link to the correct destination.

Examples include:

* Enquire   
* Availability  
* Book Now  
* Gallery  
* VR Tour  
* Things To Do  
* Key Facts

Links should be generated dynamically using the house slug so no manual URL editing is required.

---

# **Parent / Child Visibility Inheritance**

Visibility should inherit from the parent page.

If the parent page changes from:

* Published → Private

all child pages should automatically become Private.

Likewise, if the parent becomes Published again, all child pages should inherit the same visibility state.

The parent page should remain the single source of truth for house visibility.

# **Task 436 \- VR**

Ensure the Ensure that the VR tour functionality from task 436 is also included as part of the blueprint development

---

# **Acceptance Criteria**

A completed Blueprint should:

* Generate the parent house page.  
* Generate all five child pages.  
* Use the correct MASTER template for every page.  
* Create every page as Draft.  
* Automatically configure H1s, Meta Titles and Meta Descriptions.  
* Apply canonical, NOINDEX and sitemap rules to Book and Availability pages.  
* Automatically duplicate the first five gallery images to the fader image set on the Gallery and Availability pages.  
* Automatically inherit "Houses You May Also Like" across all child pages.  
* Configure House Title Banner links automatically.  
* Apply mobile layout defaults (centralised content and stacked buttons).  
* Include Task \#421 and Task \#424 behaviour by default.  
* Automatically map all navigation buttons to the correct house pages.  
* Ensure child page visibility always inherits the parent page's visibility.  
* Require minimal manual configuration after Blueprint creation, allowing editors to focus solely on adding page content.

I think on the KF for the blueprint can we adapt the sizing of the table for Bedrooms please.  
We used to have uneven column widths as the Sleeps column doesn't need to be too wide.

Our old html template used to have 25% for bedrooms, 15% for sleeps, 30% for beds and 30% for features of the whole column width.