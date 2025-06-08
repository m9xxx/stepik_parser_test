# Контекстная диаграмма потоков данных

```mermaid
graph TD
    subgraph "External Systems"
        STEPIK[Stepik API]
        SKILLBOX[Skillbox API]
        GEEKBRAINS[GeekBrains API]
        USER[Web Browser]
    end

    subgraph "Course Aggregator System"
        SYSTEM[Course Aggregator]
    end

    subgraph "Data Storage"
        DB[(MySQL Database)]
        JSON[JSON Files]
    end

    %% Data Flows
    STEPIK -->|Course Data| SYSTEM
    SKILLBOX -->|Course Data| SYSTEM
    GEEKBRAINS -->|Course Data| SYSTEM
    
    SYSTEM -->|Store Parsed Data| JSON
    SYSTEM -->|Store/Retrieve| DB
    
    USER -->|HTTP Requests| SYSTEM
    SYSTEM -->|JSON Responses| USER

    %% Styles
    classDef external fill:#f9f,stroke:#333,stroke-width:2px;
    classDef system fill:#9f9,stroke:#333,stroke-width:2px;
    classDef storage fill:#99f,stroke:#333,stroke-width:2px;
    
    class STEPIK,SKILLBOX,GEEKBRAINS,USER external;
    class SYSTEM system;
    class DB,JSON storage; 