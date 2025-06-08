graph TB
    subgraph "Frontend Layer"
        CLIENT[Web Browser]
        STATIC[Static Files<br/>Apache/Nginx]
    end

    subgraph "Application Layer"
        APP[PHP Application<br/>Apache + PHP-FPM]
        CACHE[File Cache<br/>JSON Storage]
    end

    subgraph "Database Layer"
        MYSQL[MySQL Database]
        BACKUP[Daily Backups]
    end

    %% Connections
    CLIENT -->|HTTPS| STATIC
    CLIENT -->|HTTPS| APP
    APP -->|File I/O| CACHE
    APP -->|TCP| MYSQL
    MYSQL -.->|Backup| BACKUP

    %% Styles
    classDef frontend fill:#ff9,stroke:#333,stroke-width:2px
    classDef app fill:#9f9,stroke:#333,stroke-width:2px
    classDef db fill:#99f,stroke:#333,stroke-width:2px
    
    class CLIENT,STATIC frontend
    class APP,CACHE app
    class MYSQL,BACKUP db 