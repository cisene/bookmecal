# Appendix B: Regional Configuration & Localization Schemas

To ensure clean separation of concerns, multi-region scalability, and reliable validation across different countries, the system externalizes regional data into two distinct JSON schema specifications: **Holidays** and **Locales**.

## 1. Holiday Configuration Schema (`schemas/holiday.schema.json`)
Validates date-specific closures, public holidays, and red days (*röda dagar*) for a given country code (e.g., `holidays/se.json`).

```json
{
  "$schema": "[https://json-schema.org/draft/2020-12/schema](https://json-schema.org/draft/2020-12/schema)",
  "title": "HolidayConfiguration",
  "type": "object",
  "required": ["country", "code", "holidays"],
  "properties": {
    "country": {
      "type": "string",
      "description": "Full name of the country (e.g., Sweden)."
    },
    "code": {
      "type": "string",
      "pattern": "^[A-Z]{2}$",
      "description": "ISO 3166-1 alpha-2 country code (e.g., SE)."
    },
    "holidays": {
      "type": "array",
      "description": "List of public holidays and closures.",
      "items": {
        "type": "object",
        "required": ["date", "name"],
        "properties": {
          "date": {
            "type": "string",
            "format": "date",
            "description": "Holiday date in YYYY-MM-DD format."
          },
          "name": {
            "type": "string",
            "description": "Description or name of the holiday."
          }
        }
      }
    }
  }
}